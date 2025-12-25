<?php
// Навигационная панель
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?= SITE_NAME ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Главная</a>
                </li>
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Панель</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <form class="d-flex me-3" action="search.php" method="GET">
                <input class="form-control me-2" type="search" name="q" placeholder="Поиск...">
                <button class="btn btn-outline-light" type="submit">Найти</button>
            </form>
            
            <ul class="navbar-nav">
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <img src="<?= UPLOAD_DIR . $_SESSION['avatar'] ?>" 
                                 class="avatar me-1" style="width: 30px; height: 30px;">
                            <?= escape($_SESSION['username']) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Выход</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Вход</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Регистрация</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>