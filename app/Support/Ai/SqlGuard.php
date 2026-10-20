<?php

namespace App\Support\Ai;

use App\Enums\SqlDialect;
use App\Exceptions\Ai\UnsafeSqlException;

/**
 * Gate lexical do SQL gerado para o alvo `sql` (ADR-041).
 *
 * O SQL nunca e executado por nos; o gate existe para nao devolver uma
 * instrucao de escrita como se fosse leitura. Ele nao e um parser por dialeto:
 * remove strings/comentarios, exige uma unica instrucao comecando em
 * `SELECT`/`WITH` e recusa os verbos e funcoes que mudam dados ou tocam o
 * servidor.
 *
 * A divisao por `;` e feita no texto cru, antes de remover strings. Isso e
 * deliberadamente conservador: um `;` dentro de um literal faz a query ser
 * recusada, mas nenhum `;` escondido deixa duas instrucoes passarem.
 */
final class SqlGuard
{
    private const FORBIDDEN_KEYWORDS = '/\b(ALTER|ANALYZE|ATTACH|BEGIN|BULK|CALL|COMMENT|COMMIT|COPY|CREATE|DBCC|DEALLOCATE|DECLARE|DELETE|DETACH|DISCARD|DROP|EXEC|EXECUTE|FLUSH|GRANT|INSERT|KILL|LISTEN|LOAD|LOCK|MERGE|NOTIFY|OPTIMIZE|PRAGMA|PREPARE|RECONFIGURE|REINDEX|RELEASE|RENAME|REPAIR|RESET|REVOKE|ROLLBACK|SAVEPOINT|SET|SHUTDOWN|TRUNCATE|UNLISTEN|UNLOCK|UPDATE|UPSERT|USE|VACUUM|WAITFOR)\b/i';

    private const DANGEROUS_FUNCTIONS = '/\b(BENCHMARK|DBLINK|LOAD_FILE|LO_EXPORT|LO_IMPORT|NEXTVAL|OPENROWSET|OPENDATASOURCE|PG_ADVISORY_LOCK|PG_ADVISORY_XACT_LOCK|PG_LS_DIR|PG_READ_BINARY_FILE|PG_READ_FILE|PG_SLEEP|SETVAL|SLEEP|SP_EXECUTESQL|XP_CMDSHELL|XP_DIRTREE|XP_REGREAD|XP_REGWRITE)\s*\(/i';

    public function assertSafe(string $sql, SqlDialect $dialect): void
    {
        $sql = trim($sql);

        if ($sql === '') {
            throw UnsafeSqlException::because('empty');
        }

        $this->assertSingleStatement($sql);

        // MySQL/MariaDB executam o conteudo de `/*! ... */` como SQL, ao
        // contrario de um comentario comum. Recusar e mais seguro do que tentar
        // distinguir hint de instrucao.
        if ($dialect === SqlDialect::Mysql && preg_match('/\/\*M?!/i', $sql) === 1) {
            throw UnsafeSqlException::because('executable_comment');
        }

        $statement = $this->strip($sql, $dialect);
        $statement = trim(preg_replace('/\s+/', ' ', $statement) ?? $statement);

        if (preg_match('/^(SELECT|WITH)\b/i', $statement) !== 1) {
            throw UnsafeSqlException::because('not_select');
        }

        if (preg_match(self::FORBIDDEN_KEYWORDS, $statement) === 1) {
            throw UnsafeSqlException::because('forbidden_keyword');
        }

        if (preg_match('/\bINTO\b/i', $statement) === 1) {
            throw UnsafeSqlException::because('into_clause');
        }

        if (preg_match('/\b(OUTFILE|DUMPFILE)\b/i', $statement) === 1) {
            throw UnsafeSqlException::because('file_clause');
        }

        if (preg_match(self::DANGEROUS_FUNCTIONS, $statement) === 1) {
            throw UnsafeSqlException::because('dangerous_function');
        }
    }

    private function assertSingleStatement(string $sql): void
    {
        $parts = explode(';', $sql);
        $last = array_pop($parts);

        // `explode` sempre devolve ao menos um item: o ultimo trecho e a
        // instrucao sem o `;` final.
        if (trim($last) !== '') {
            $parts[] = $last;
        }

        $statements = array_filter($parts, static fn (string $part): bool => trim($part) !== '');

        if (count($statements) !== 1) {
            throw UnsafeSqlException::because('multiple_statements');
        }
    }

    /**
     * Troca strings e comentarios por espacos, guardando a estrutura.
     *
     * O escape por barra invertida so vale no MySQL: no Postgres/SQLite ele e
     * literal, e trata-lo como escape esconderia o fim de uma string (e o
     * comeco de outra instrucao) do resto do gate.
     */
    private function strip(string $sql, SqlDialect $dialect): string
    {
        $output = '';
        $length = strlen($sql);
        $state = 'normal';
        $commentDepth = 0;
        $backslashEscapes = $dialect === SqlDialect::Mysql;

        for ($i = 0; $i < $length; $i++) {
            $character = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($state === 'normal') {
                if ($character === "'") {
                    $state = 'single';
                    $output .= ' ';
                } elseif ($character === '"') {
                    $state = 'double';
                    $output .= ' ';
                } elseif ($character === '`') {
                    $state = 'backtick';
                    $output .= ' ';
                } elseif ($character === '[' && $dialect === SqlDialect::Sqlsrv) {
                    $state = 'bracket';
                    $output .= ' ';
                } elseif ($character === '-' && $next === '-') {
                    $state = 'line';
                    $output .= ' ';
                    $i++;
                } elseif ($character === '#') {
                    $state = 'line';
                    $output .= ' ';
                } elseif ($character === '/' && $next === '*') {
                    $state = 'block';
                    $commentDepth = 1;
                    $output .= ' ';
                    $i++;
                } else {
                    $output .= $character;
                }

                continue;
            }

            if ($state === 'line') {
                if ($character === "\n" || $character === "\r") {
                    $state = 'normal';
                    $output .= ' ';
                }

                continue;
            }

            if ($state === 'block') {
                if ($character === '/' && $next === '*') {
                    $commentDepth++;
                    $i++;
                } elseif ($character === '*' && $next === '/') {
                    $commentDepth--;
                    $i++;

                    if ($commentDepth === 0) {
                        $state = 'normal';
                    }
                }

                continue;
            }

            $closing = match ($state) {
                'single' => "'",
                'double' => '"',
                'backtick' => '`',
                default => ']',
            };

            if ($character === $closing) {
                if ($next === $closing) {
                    $i++;

                    continue;
                }

                $state = 'normal';

                continue;
            }

            if ($backslashEscapes && $state !== 'backtick' && $state !== 'bracket' && $character === '\\') {
                $i++;
            }
        }

        if ($state !== 'normal') {
            throw UnsafeSqlException::because('unterminated_literal');
        }

        return $output;
    }
}
