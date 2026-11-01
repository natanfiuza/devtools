# Instalar ImageMagick e Imagick no Ubuntu

## 1. Atualizar pacotes
sudo apt update && sudo apt upgrade -y

## 2. Instalar ImageMagick e extensão PHP
Para PHP 8.3:
sudo apt install -y imagemagick php8.3-imagick

Se o pacote exato não existir:
sudo apt install -y imagemagick php-imagick

## 3. Habilitar extensão
sudo phpenmod -v 8.3 imagick

## 4. Reiniciar PHP-FPM
sudo systemctl restart php8.3-fpm

Se usar Apache:
sudo systemctl restart apache2

Se usar Nginx:
sudo systemctl restart php8.3-fpm

## 5. Verificar
php -v
php -m | grep imagick
php -r "var_dump(extension_loaded('imagick'));"

## 6. Teste rápido
php -r '$i=new Imagick(); $i->newPseudoImage(10,10,"xc:white"); echo "Imagick OK".PHP_EOL;'

## 7. Permissões
sudo chown -R www-data:www-data /var/www/devtools/storage
sudo chmod -R ug+rwx /var/www/devtools/storage

## 8. Problemas comuns
- extensão não aparece: conferir php -m e reiniciar FPM.
- erro de delegado SVG: instalar librsvg2-bin.
sudo apt install -y librsvg2-bin
- limite de memória: ajustar memory_limit no php.ini.
