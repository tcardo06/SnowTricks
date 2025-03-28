# Projet SnowTricks

## Résumé

SnowTricks est une application Symfony de gestion de figures de snowboard. L’application permet aux utilisateurs de consulter les figures, de discuter autour d’elles et, pour les utilisateurs authentifiés, de créer, modifier ou supprimer des figures. Ce README vous guidera à travers l'installation locale avec XAMPP, la configuration de la base de données, le chargement des données initiales et la configuration pour tester l'envoi d'emails avec MailHog.

## Table des matières

- [Installation locale avec XAMPP](#installation-locale-avec-xampp)
  - [Prérequis (local)](#prérequis-local)
  - [Étapes d'installation (local)](#étapes-dinstallation-local)
  - [Configuration de la base de données et des variables d'environnement](#configuration-de-la-base-de-données-et-des-variables-denvironnement)
  - [Chargement des données initiales](#chargement-des-données-initiales)
- [Tester les emails localement avec MailHog](#tester-les-emails-localement-avec-mailhog)
- [Dépannage](#dépannage)
- [Remarques supplémentaires](#remarques-supplémentaires)

## Installation locale avec XAMPP

### Prérequis (local)
- XAMPP (avec PHP 7.4 ou supérieur)
- Composer
- Git (optionnel)

### Étapes d'installation (local)

1. **Téléchargez et installez XAMPP**  
   Rendez-vous sur le [site officiel d'Apache Friends](https://www.apachefriends.org/index.html) et installez XAMPP.

2. **Clonez ou téléchargez le projet**  
   Placez le projet dans le dossier `htdocs` de XAMPP. Par exemple, en ligne de commande :
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/tcardo06/SnowTricks.git
Ou téléchargez et extrayez le ZIP dans ce dossier.

Installez les dépendances
Ouvrez une invite de commande dans le dossier du projet et exécutez :

```bash
composer install
```

### Configuration de la base de données et des variables d'environnement

Lancez XAMPP et démarrez Apache et MySQL.

Ouvrez phpMyAdmin (http://localhost/phpmyadmin) et créez une nouvelle base de données (par exemple, snowtricks).

Créez un fichier .env.local en copiant le fichier .env et modifiez la variable DATABASE_URL pour correspondre à vos paramètres MySQL. Par exemple :

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/snowtricks?charset=utf8mb4"
```

Vérifiez que les autres variables (APP_ENV, APP_SECRET, MAILER_DSN, etc.) sont correctement définies.

### Chargement des données initiales

Le projet inclut un jeu de données initiales (fixtures) avec l’ensemble des figures de snowboard et un utilisateur admin.

Pour charger ces données, exécutez la commande suivante depuis le dossier du projet :

```bash
php bin/console doctrine:fixtures:load
```

Cela créera :

Un utilisateur admin (avec le nom admin et l'email admin@example.com)

20 figures de snowboard avec des noms, des descriptions et des groupes attribués.

## Tester les emails localement avec MailHog

Pour tester l'envoi d'emails en local, nous utilisons MailHog.

### Étapes d'installation de MailHog

Téléchargez MailHog depuis la page des releases pour votre système (par exemple, pour Windows, téléchargez MailHog_windows_386.exe).

Placez le fichier binaire dans un dossier accessible.

Lancez MailHog en exécutant :

```bash
./MailHog_windows_386.exe
```

MailHog sera accessible via http://localhost:8025.

### Configuration de l'application pour MailHog

Dans votre fichier .env (ou .env.local), assurez-vous que la variable suivante est définie :

```dotenv
MAILER_DSN=smtp://127.0.0.1:1025
```

Cela permettra à l’application d’envoyer les emails à MailHog pour les visualiser dans l'interface web.

## Dépannage

Problèmes SSL avec Composer :
Si vous obtenez une erreur SSL lors de l’exécution de composer install :

Téléchargez le fichier cacert.pem depuis https://curl.se/docs/caextract.html.

Placez-le, par exemple, dans C:\xampp\php\extras\ssl\cacert.pem.

Dans votre fichier php.ini (situé dans C:\xampp\php\), ajoutez ou modifiez :

```ini
curl.cainfo="C:\xampp\php\extras\ssl\cacert.pem"
openssl.cafile="C:\xampp\php\extras\ssl\cacert.pem"
```

Redémarrez XAMPP et réessayez composer install.

Problèmes de connexion à la base de données :
Vérifiez que la variable DATABASE_URL est correcte et que MySQL est démarré.

Consulter les logs :
Pour des erreurs supplémentaires, consultez les logs d’Apache et les logs de Symfony situés dans le dossier var/log/.

## Remarques supplémentaires

Jeu de données initiales :
Les fixtures fournissent 20 figures de snowboard et un utilisateur admin. Vous pouvez modifier ces fichiers dans src/DataFixtures/UserFixtures.php et src/DataFixtures/TrickFixtures.php pour ajouter davantage de réalisme aux données (noms, descriptions, groupes, etc.).

Limitation de la taille des images :
L'application limite la taille des images à 1,4 Mo via des contrôles côté client (JavaScript) et côté serveur (contrôles dans le formulaire).

Fonctionnalités :
SnowTricks permet de consulter, créer, modifier et supprimer des figures, de gérer les médias (images et vidéos) et de participer aux discussions autour de chaque figure.
