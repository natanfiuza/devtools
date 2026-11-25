/**
 * Os exemplos do botao "Exemplo": um par por modo.
 *
 * O de texto muda uma linha, remove outra e acrescenta uma - as tres leituras
 * do diff; o de JSON muda um valor, troca um item de lista e acrescenta uma
 * chave **e** reordena as chaves, que no modo JSON nao e mudanca nenhuma. O
 * par de JSON e o mesmo dado do par de texto nas partes que coincidem, para o
 * "trocar de modo" nao assustar.
 */

export const TEXT_EXAMPLE_LEFT = `name = devtools
port = 8080
debug = false
workers = 2
log = info`;

export const TEXT_EXAMPLE_RIGHT = `name = devtools
port = 8080
debug = true
workers = 4
log = info
region = sa-east-1`;

export const JSON_EXAMPLE_LEFT = `{
  "name": "devtools",
  "port": 8080,
  "flags": {
    "debug": false,
    "beta": true
  },
  "tags": ["json", "yaml"]
}`;

export const JSON_EXAMPLE_RIGHT = `{
  "port": 8080,
  "name": "devtools",
  "flags": {
    "debug": true,
    "beta": true
  },
  "tags": ["json", "toml"],
  "owner": "Ada"
}`;
