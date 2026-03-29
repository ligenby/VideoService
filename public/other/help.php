<?php

$additional_styles = ['/css/help.css'];
$body_class = 'help-page';

require_once __DIR__ . '/../../src/core/config.php';
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';
?>

<main class="main-content">
    <div class="help-page-container">
        
        <div class="help-hero-wrapper animated-bg">
            <div class="help-hero-content">
                <h1>Центр поддержки</h1>
                <p class="hero-subtitle">Добро пожаловать! Найдите здесь ответы на самые популярные вопросы.</p>
                
                <div class="search-bar-container">
                    <img src="/images/search.png" class="search-icon-img" alt="Поиск">
                    <input type="text" id="faq-search" placeholder="Например: 'Как загрузить видео?'" autocomplete="off">
                    <div id="search-suggestions" class="search-suggestions"></div>
                </div>
            </div>
        </div>

        <div class="quick-links-grid">
            <a href="/pages/you.php" class="quick-link-card">
                <div class="icon-circle blue">
                    <img src="/images/user.png" alt="Аккаунт" class="quick-link-icon">
                </div>
                <div class="link-text">
                    <h3>Мой аккаунт</h3>
                    <p>Настройки и вход</p>
                </div>
            </a>
            <a href="/upload.php" class="quick-link-card">
                <div class="icon-circle blue">
                    <img src="/images/studio.png" alt="Студия" class="quick-link-icon">
                </div>
                <div class="link-text">
                    <h3>Студия</h3>
                    <p>Загрузка контента</p>
                </div>
            </a>
            <a href="/other/reviews.php" class="quick-link-card">
                <div class="icon-circle blue">
                    <img src="/images/support.png" alt="Поддержка" class="quick-link-icon">
                </div>
                <div class="link-text">
                    <h3>Поддержка</h3>
                    <p>Связаться с нами</p>
                </div>
            </a>
        </div>

        <div class="faq-section-title">База знаний</div>
        
        <div class="faq-container">
            
            <div class="faq-category" id="cat-general">
                <h2 class="category-title">О сервисе LensEra</h2>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Что такое LensEra?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>LensEra — это современная видеоплатформа для творчества и общения. Мы создаем пространство, где каждый голос имеет значение. Здесь вы можете не только смотреть ролики, но и находить единомышленников, создавать сообщества и делиться своими идеями с миром.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Нужно ли платить за использование?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Нет, основные функции сервиса бесплатны. Вы можете смотреть видео в высоком качестве, создавать каналы, загружать контент и общаться в комментариях без какой-либо оплаты.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Есть ли мобильное приложение?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>На данный момент мы активно работаем над мобильным приложением для iOS и Android. Пока вы можете пользоваться удобной мобильной версией нашего сайта через браузер на вашем телефоне.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="faq-category" id="cat-account">
                <h2 class="category-title">Аккаунт и Канал</h2>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Как создать канал?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <ol>
                                <li>Зарегистрируйтесь или войдите в аккаунт.</li>
                                <li>Нажмите на иконку профиля или перейдите на страницу <a href="/pages/you.php">"Вы"</a>.</li>
                                <li>Нажмите кнопку "Создать канал", придумайте название и загрузите аватар.</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Как получить галочку верификации?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Значок верификации выдается известным авторам, брендам и общественным деятелям. Чтобы подать заявку, ваш канал должен иметь более 100 000 подписчиков и быть активным. Заявку можно отправить через службу поддержки.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Я забыл пароль, как восстановить?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>На странице входа нажмите ссылку "Забыли пароль?". Введите ваш Email, и мы отправим инструкцию по сбросу пароля. Если письма нет, проверьте папку "Спам".</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Как удалить свой аккаунт?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Перейдите в <a href="/other/settings.php?tab=danger">Настройки -> Удаление аккаунта</a>. Внимательно прочитайте предупреждение: это действие необратимо и удалит все ваши видео и комментарии.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="faq-category" id="cat-video">
                <h2 class="category-title">Загрузка и управление видео</h2>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Какие требования к загружаемым файлам?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Мы поддерживаем форматы MP4, AVI, MOV, MKV. Максимальный размер файла — 2 ГБ. Рекомендуемые кодеки: видео H.264, аудио AAC.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Что такое "Доступ по ссылке"?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Это режим приватности, при котором видео не отображается в поиске и на вашем канале. Его могут посмотреть только те люди, которым вы отправили прямую ссылку на ролик.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Как изменить название или описание видео?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>В данный момент редактирование метаданных доступно только при загрузке. Функция редактирования уже опубликованных видео находится в разработке и появится в ближайшем обновлении "Творческой студии".</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Почему качество видео низкое после загрузки?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Сразу после загрузки видео обрабатывается в низком разрешении для быстрого доступа. Высокие качества (HD, 4K) становятся доступны спустя некоторое время, в зависимости от размера файла и нагрузки на сервер.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="faq-category" id="cat-safety">
                <h2 class="category-title">Правила и безопасность</h2>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Как пожаловаться на видео?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Под плеером нажмите кнопку "Еще" (три точки) -> "Пожаловаться". Выберите причину нарушения, и наши модераторы проверят контент в течение 24 часов.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <span>Что делать с оскорбительными комментариями?</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Мы не поддерживаем токсичное поведение. Вы можете пожаловаться на комментарий, открыв меню рядом с ним. Владельцы каналов также могут удалять любые комментарии под своими видео.</p>
                        </div>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Авторские права и страйки</span>
                        <div class="icon-plus"></div>
                    </div>
                    <div class="faq-answer">
                        <div class="answer-inner">
                            <p>Если вы используете чужой контент (музыку, видео) без разрешения, правообладатель может подать жалобу. Это приведет к удалению видео и получению предупреждения (страйка). Три страйка приведут к блокировке канала.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="help-footer-banner">
            <h3>Не нашли ответ?</h3>
            <p class="help-footer-text">Свяжитесь с нашей службой поддержки для решения вашей проблемы.</p>
            <div class="help-footer-actions">
                <a href="/other/reviews.php" class="help-btn">Написать в поддержку</a>
                <a href="/info/contacts.php" class="help-btn secondary">Контакты</a>
            </div>
        </div>

    </div>
</main>
</div> 

<script src="/js/help.js"></script>
<script src="/js/script.js"></script>
</body>
</html>