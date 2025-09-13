FROM php:8.2-apache

# 1. Instalar dependências do sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
    libldap2-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    tzdata \
    default-mysql-client \
    cron \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar e instalar extensões PHP
RUN docker-php-ext-configure ldap \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) ldap mysqli pdo pdo_mysql gd zip \
    && docker-php-ext-enable ldap mysqli pdo pdo_mysql gd zip

# NOVO: Copiar as configurações customizadas do PHP para aumentar o limite de upload
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Configurar o fuso horário para a aplicação 
ENV TZ=America/Sao_Paulo
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone 

# 3. Instalar o Composer (MÉTODO CORRIGIDO E OFICIAL)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. Definir o diretório de trabalho
WORKDIR /var/www/html

# 5. Adicionar configuração do Cron
COPY delete-images-cron /etc/cron.d/delete-images-cron
RUN chmod 0644 /etc/cron.d/delete-images-cron \
    && crontab /etc/cron.d/delete-images-cron \
    && touch /var/log/cron.log

# 6. Adicionar entrypoint customizado para iniciar o cron
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# 7. Manter o comando original para iniciar o Apache
CMD ["apache2-foreground"]