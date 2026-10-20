<?php

namespace App\Support\Ai;

use App\Enums\SqlDialect;
use App\Exceptions\Ai\InvalidContractException;
use JsonException;

/**
 * Valida o JSON que o modelo devolve (SPEC secao 6).
 *
 * O contrato e estreito de proposito: a tela, o cache e o gate de SQL dependem
 * destes campos. O que nao estiver na forma vira `invalid_contract`; nada do
 * texto recebido entra na excecao.
 */
final class ContractValidator
{
    private const MAX_LIST_ITEMS = 50;

    private const MAX_LIST_ITEM_CHARS = 1000;

    private const MAX_CLARIFICATION_CHARS = 4000;

    private const MAX_OUTPUTS = 20;

    /**
     * @param  array<mixed>  $data
     */
    public function validate(array $data, SqlDialect $dialect, string $targetSlug): AiResult
    {
        $this->assertSize($data);

        foreach (['sql', 'dialect', 'assumptions', 'warnings', 'tables_used', 'needs_clarification', 'clarification', 'outputs'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw InvalidContractException::because('missing_'.$field);
            }
        }

        $sql = $data['sql'];
        $declaredDialect = $data['dialect'];
        $needsClarification = $data['needs_clarification'];

        if (! is_string($sql)) {
            throw InvalidContractException::because('sql_not_string');
        }

        if (! is_string($declaredDialect)) {
            throw InvalidContractException::because('dialect_not_string');
        }

        if (strtolower(trim($declaredDialect)) !== $dialect->value) {
            throw InvalidContractException::because('dialect_mismatch');
        }

        if (! is_bool($needsClarification)) {
            throw InvalidContractException::because('needs_clarification_not_boolean');
        }

        $assumptions = $this->stringList($data['assumptions'], 'assumptions');
        $warnings = $this->stringList($data['warnings'], 'warnings');
        $tablesUsed = $this->stringList($data['tables_used'], 'tables_used');
        $clarification = $this->clarification($data['clarification']);
        $outputs = $this->outputs($data['outputs']);

        $sql = trim($sql);

        if ($needsClarification) {
            if ($sql !== '') {
                throw InvalidContractException::because('sql_with_clarification');
            }

            if ($clarification === null) {
                throw InvalidContractException::because('missing_clarification');
            }
        } else {
            if ($sql === '') {
                throw InvalidContractException::because('empty_sql');
            }

            if ($clarification !== null) {
                throw InvalidContractException::because('unexpected_clarification');
            }

            if (! isset($outputs[$targetSlug]) || $outputs[$targetSlug] === '') {
                throw InvalidContractException::because('missing_target_output');
            }
        }

        return new AiResult(
            $sql,
            $dialect,
            $assumptions,
            $warnings,
            $tablesUsed,
            $needsClarification,
            $clarification,
            $outputs,
        );
    }

    /**
     * @param  array<mixed>  $data
     */
    private function assertSize(array $data): void
    {
        try {
            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidContractException::because('not_serializable');
        }

        $limit = max(1, (int) config('ai.limits.max_output_bytes', 32768));

        if (strlen($encoded) > $limit) {
            throw InvalidContractException::because('too_large');
        }
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidContractException::because($field.'_not_list');
        }

        if (count($value) > self::MAX_LIST_ITEMS) {
            throw InvalidContractException::because($field.'_too_long');
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw InvalidContractException::because($field.'_item_not_string');
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            if (mb_strlen($item, 'UTF-8') > self::MAX_LIST_ITEM_CHARS) {
                throw InvalidContractException::because($field.'_item_too_long');
            }

            $items[] = $item;
        }

        return $items;
    }

    private function clarification(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidContractException::because('clarification_not_string');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') > self::MAX_CLARIFICATION_CHARS) {
            throw InvalidContractException::because('clarification_too_long');
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function outputs(mixed $value): array
    {
        if (! is_array($value)) {
            throw InvalidContractException::because('outputs_not_object');
        }

        if (count($value) > self::MAX_OUTPUTS) {
            throw InvalidContractException::because('outputs_too_many');
        }

        $outputs = [];
        $limit = max(1, (int) config('ai.limits.max_output_bytes', 32768));

        foreach ($value as $slug => $code) {
            if (! is_string($slug) || preg_match('/^[a-z0-9_]{1,32}$/', $slug) !== 1) {
                throw InvalidContractException::because('output_key_invalid');
            }

            if (! is_string($code)) {
                throw InvalidContractException::because('output_not_string');
            }

            $code = trim($code);

            if ($code === '') {
                throw InvalidContractException::because('output_empty');
            }

            if (strlen($code) > $limit) {
                throw InvalidContractException::because('output_too_large');
            }

            $outputs[$slug] = $code;
        }

        return $outputs;
    }
}
