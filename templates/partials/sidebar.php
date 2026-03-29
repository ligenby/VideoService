<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$page_container_classes = [];

if ($currentPage === 'watch.php') {
    $page_container_classes[] = 'watch-mode';
    $page_container_classes[] = 'sidebar-collapsed'; 
}
?>

<!-- Выводим класс в контейнер -->
<div class="page-container <?php echo implode(' ', $page_container_classes); ?>">
    <aside class="sidebar">
        <!-- МИНИ-САЙДБАР (отображается, когда сайдбар свернут) -->
        <div class="sidebar-mini">
             <a href="/" class="sidebar-item <?php if($currentPage == 'index.php') echo 'active'; ?>" data-page="home">
                <img class="icon-img" src="/images/home.png" alt="Главная">
                <span>Главная</span>
            </a>
            <a href="/pages/you.php" class="sidebar-item <?php if($currentPage == 'you.php') echo 'active'; ?>" data-page="you">
                <img class="icon-img" src="/images/profile.png" alt="Вы">
                <span>Вы</span>
            </a>
            <a href="/pages/subscriptions.php" class="sidebar-item <?php if($currentPage == 'subscriptions.php') echo 'active'; ?>" data-page="subscriptions">
                <img class="icon-img" src="/images/subscrieb.png" alt="Подписки">
                <span>Подписки</span>
            </a>
            <a href="/pages/history.php" class="sidebar-item <?php if($currentPage == 'history.php') echo 'active'; ?>" data-page="history">
                <img class="icon-img" src="/images/history.png" alt="История">
                <span>История</span>
            </a>
        </div>
        
        <!-- ПОЛНЫЙ САЙДБАР (отображается по умолчанию) -->
        <div class="sidebar-full">
            <a href="/" class="sidebar-item <?php if($currentPage == 'index.php') echo 'active'; ?>" data-page="home">
                <img class="icon-img" src="/images/home.png" alt="Главная">
                <span>Главная</span>
            </a>
            <a href="/pages/you.php" class="sidebar-item <?php if($currentPage == 'you.php') echo 'active'; ?>" data-page="you">
                <img class="icon-img" src="/images/profile.png" alt="Вы">
                <span>Вы</span>
            </a>
            
            <hr class="sidebar-divider">

            <div class="sidebar-section">
                <h3 class="sidebar-heading">Моё</h3>
                <a href="/pages/subscriptions.php" class="sidebar-item <?php if($currentPage == 'subscriptions.php') echo 'active'; ?>" data-page="subscriptions">
                    <img class="icon-img" src="/images/subscrieb.png" alt="Подписки">
                    <span>Подписки</span>
                </a>
                <a href="/pages/history.php" class="sidebar-item <?php if($currentPage == 'history.php') echo 'active'; ?>" data-page="history">
                    <img class="icon-img" src="/images/history.png" alt="История">
                    <span>История</span>
                </a>
                <a href="/pages/playlists.php" class="sidebar-item <?php if($currentPage == 'playlists.php') echo 'active'; ?>" data-page="playlists">
                    <img class="icon-img" src="/images/playlist.png" alt="Плейлисты">
                    <span>Плейлисты</span>
                </a>
                <a href="/pages/watch_later.php" class="sidebar-item <?php if($currentPage == 'watch_later.php') echo 'active'; ?>" data-page="watch_later">
                    <img class="icon-img" src="/images/watch_later.png" alt="Смотреть позже">
                    <span>Смотреть позже</span>
                </a>
            </div>

            <hr class="sidebar-divider">

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/pages/liked.php" class="sidebar-item <?php if($currentPage == 'liked.php') echo 'active'; ?>" data-page="liked">
                    <img class="icon-img" src="/images/like.png" alt="Понравившиеся"> 
                    <span>Понравившиеся</span>
                </a>
            <?php else: ?>
                <div class="login-prompt">
                    <p>Вы сможете ставить отметки "Нравится", писать комментарии и подписываться на каналы.</p>
                    <a href="/login.php" class="login-button">
                        <img class="icon-img" src="/images/profile.png" alt="Войти">
                        <span>Войти</span>
                    </a>
                </div>
            <?php endif; ?>

            <hr class="sidebar-divider">

            <div class="sidebar-section">
                <h3 class="sidebar-heading">Категории</h3>
                <a href="/navigation/music.php" class="sidebar-item <?php if($currentPage == 'music.php') echo 'active'; ?>" data-page="music">
                    <img class="icon-img" src="/images/music.png" alt="Музыка">
                    <span>Музыка</span>
                </a>
                <a href="/navigation/movie.php" class="sidebar-item <?php if($currentPage == 'movie.php') echo 'active'; ?>" data-page="movie">
                    <img class="icon-img" src="/images/movies.png" alt="Фильмы и сериалы">
                    <span>Фильмы и сериалы</span>
                </a>
                <a href="/navigation/games.php" class="sidebar-item <?php if($currentPage == 'games.php') echo 'active'; ?>" data-page="games">
                    <img class="icon-img" src="/images/games.png" alt="Видеоигры">
                    <span>Видеоигры</span>
                </a>
                <a href="/navigation/news.php" class="sidebar-item <?php if($currentPage == 'news.php') echo 'active'; ?>" data-page="news">
                    <img class="icon-img" src="/images/news.png" alt="Новости">
                    <span>Новости</span>
                </a>
                <a href="/navigation/sport.php" class="sidebar-item <?php if($currentPage == 'sport.php') echo 'active'; ?>" data-page="sport">
                    <img class="icon-img" src="/images/sport.png" alt="Спорт">
                    <span>Спорт</span>
                </a>
                <a href="/navigation/courses.php" class="sidebar-item <?php if($currentPage == 'courses.php') echo 'active'; ?>" data-page="courses">
                    <img class="icon-img" src="/images/courses.png" alt="Курсы">
                    <span>Курсы</span>
                </a>
                <a href="/navigation/fashion.php" class="sidebar-item <?php if($currentPage == 'fashion.php') echo 'active'; ?>" data-page="fashion">
                    <img class="icon-img" src="/images/fashion.png" alt="Мода и красота">
                    <span>Мода и красота</span>
                </a>
                <a href="/navigation/morecategories.php" class="sidebar-item <?php if($currentPage == 'morecategories.php') echo 'active'; ?>" data-page="more-categories">
                    <img class="icon-img" src="/images/more2.png" alt="Больше">
                    <span>Показать больше</span>
                </a>
            </div>

            <hr class="sidebar-divider">

            <div class="sidebar-section">
                <a href="/other/settings.php" class="sidebar-item <?php if($currentPage == 'settings.php') echo 'active'; ?>" data-page="settings">
                     <img class="icon-img" src="/images/settings.png" alt="Настройки">
                    <span>Настройки</span>
                </a>
                <a href="/other/complaints.php" class="sidebar-item <?php if($currentPage == 'complaints.php') echo 'active'; ?>" data-page="complaints">
                     <img class="icon-img" src="/images/complaint.png" alt="Жалобы">
                    <span>Жалобы</span>
                </a>
                <a href="/other/help.php" class="sidebar-item <?php if($currentPage == 'help.php') echo 'active'; ?>" data-page="help">
                     <img class="icon-img" src="/images/quest.png" alt="Справка">
                    <span>Вопросы и ответы</span>
                </a>
                <a href="/other/reviews.php" class="sidebar-item <?php if($currentPage == 'reviews.php') echo 'active'; ?>" data-page="reviews">
                     <img class="icon-img" src="/images/alert.png" alt="Отправить отзыв">
                    <span>Отправить отзыв</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <div class="footer-links">
                    <a href="/info/about.php">О сервисе</a>
                    <a href="/info/copyright.php">Авторские права</a>
                    <a href="/info/contacts.php">Связаться с нами</a>
                    <a href="/info/terms.php">Условия использования</a>
                    <a href="/info/privacy.php">Конфиденциальность</a>
                    <a href="/info/rules.php">Правила и безопасность</a>
                    <a href="/info/how_it_works.php">Как работает LensEra</a>
                </div>

                <p class="copyright">
                    &copy; <?php echo date("Y"); ?> Hanoma LLC LensEra by m9woh 
                </p>
            </div>
        </div>
    </aside>