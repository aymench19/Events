# 📘 Guide de travail – Projet Symfony (Event)



## 🔹 1. Cloner le projet

Chaque membre doit exécuter dans son terminal :

```
git clone https://github.com/aymench19/Events.git
cd events
```


---

## 🔹 2. Aller sur sa branche personnelle

Chaque membre **doit travailler sur sa branche uniquement** :

Aymen :

```
git checkout feature/aymen
```

Oussema :

```
git checkout feature/oussema
```

Amani :

```
git checkout feature/amani
```

Ranim :

```
git checkout feature/ranim
```

Pour vérifier la branche actuelle :

```
git branch
```

La branche avec `*` est celle sur laquelle tu travailles.

---

## 🔹 3. Configurer la base de données (phpMyAdmin)



### Étape 1 : Configurer le fichier `.env`

Dans le fichier `.env` du projet, modifier cette ligne :

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/events_db?serverVersion=8.0.32&charset=utf8mb4"
```



---

## 🔹 4. Installer les dépendances

Dans le dossier du projet :

```
composer install
```

Puis créer la base :

```
php bin/console doctrine:database:create
```

---

## 🔹 5. Lancer le serveur Symfony

```
symfony server:start
```

Puis dans le navigateur :

```
http://localhost:8000
```

---

## 🔹 6. Comment travailler proprement (IMPORTANT)

Chaque membre doit suivre ce cycle :

1. Faire des modifications
2. Ajouter les fichiers :

   ```
   git add .
   ```
3. Faire un commit :

   ```
   git commit -m "Message clair"
   ```
4. Envoyer sur GitHub :

   ```
   git push
   ```

‼️ **Interdiction de travailler sur la branche `main`**



## 🔹 8. Très important

✅ Toujours travailler sur sa branche
✅ Toujours faire un `git pull` avant de commencer
✅ Toujours faire un `git push` après finir
❌ Ne jamais toucher la branche `main`

