<?php

$page_title = "Все категории";
$additional_styles = ['/css/morecategories.css'];

require_once __DIR__ . '/../../src/core/config.php'; 
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';


// категории с счетчиком видео
$categories = [];
$sql = "

    SELECT 
        c.category_id, 
        c.name, 
        COUNT(vc.video_id) as video_count
    FROM categories c
    LEFT JOIN video_categories vc ON c.category_id = vc.category_id
    WHERE c.name != 'Общее'
    GROUP BY c.category_id, c.name
    ORDER BY c.name ASC
";
$result = $conn->query($sql);
if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// картинки для категорий
$category_images = [

    'Музыка'            => '/uploads/categories/music-bg.jpg',
    'Фильмы и сериалы'   => '/uploads/categories/movies-bg.jpg',
    'Видеоигры'         => '/uploads/categories/games-bg.jpg',
    'Новости'           => '/uploads/categories/news-bg.jpg',
    'Спорт'             => '/uploads/categories/sport-bg.jpg',
    'Курсы'             => '/uploads/categories/courses-bg.jpg',
    'Мода и красота'    => '/uploads/categories/fashion-bg.jpg',
];
$default_image = '/uploads/categories/default-bg.jpg';

// ссылки на категории
$category_links = [

    'Музыка'            => '/navigation/music.php',
    'Фильмы и сериалы'   => '/navigation/movie.php',
    'Видеоигры'         => '/navigation/games.php',
    'Новости'           => '/navigation/news.php',
    'Спорт'             => '/navigation/sport.php',
    'Курсы'             => '/navigation/courses.php',
    'Мода и красота'    => '/navigation/fashion.php',
];

?>

<main class="main-content">
    <div class="category-grid-container">
        <h1 class="page-main-title">Все категории</h1>

        <div class="category-grid">
            <?php foreach ($categories as $category): ?>
                <?php
                    // Получаем картинку и ссылку для текущей категории из массивов
                    $image_path = $category_images[$category['name']] ?? $default_image;
                    $link_path = $category_links[$category['name']] ?? '#';
                ?>
                <a href="<?php echo $link_path; ?>" class="category-card">
                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="card-background">
                    <div class="card-content">
                        <h2 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h2>
                        <p class="card-meta"><?php echo number_format($category['video_count']); ?> <?php echo get_plural_form($category['video_count'], ['видео', 'видео', 'видео']); ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</main>

</div> 

<script src="/js/script.js"></script>
</body>
</html>