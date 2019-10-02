<div align="center">
  
![hng](https://res.cloudinary.com/iambeejayayo/image/upload/v1554240066/brand-logo.png)

<br>

</div>

# Installation Guide

- You need a server, download [Wamp](http://www.wampserver.com/en/) or [Xampp](https://www.apachefriends.org/index.html)
- Install [Composer](https://getcomposer.org) &  [Laravel](https://laravel.com)
- Clone this repository into `htdocs` of `www` folder in your respective servers. <br>
- **If you have not been added to the organization, kindly work in your forked repository and open a pull request here** <br>
- Fork the repository and push to your `develop branch`
- Merge to your `master` and compare forks with the original repository
- Open a Pull Request.
- **Read [this](https://help.github.com/en/articles/creating-a-pull-request-from-a-fork) or watch [this](https://www.youtube.com/watch?v=G1I3HF4YWEw) for more help**

```bash
git clone https://github.com/hngi/hng-ojet-backend.git
```
```bash
cd hng-ojet-backend
```
```bash
composer install
```
- Rename `.env.example` to `.env` and configure your database information

```bash
php artisan passport:install
```
```bash
php artisan migrate
```
```bash
php artisan serve
```
```bash
Visit localhost:3000 in your browser
```

# Contribution Guide
