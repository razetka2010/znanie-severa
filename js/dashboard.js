// Скрипт для панели управления
document.addEventListener('DOMContentLoaded', function() {
    console.log('Панель управления загружена');

    // Анимация появления элементов
    const animateElements = document.querySelectorAll('.stat-card, .action-card');
    animateElements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';

        setTimeout(() => {
            element.style.transition = 'all 0.5s ease';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Обновление времени активности
    function updateActivityTimes() {
        const timeElements = document.querySelectorAll('.activity-time');
        timeElements.forEach(element => {
            // Здесь можно добавить логику обновления времени
            console.log('Обновление времени активности');
        });
    }

    // Инициализация
    updateActivityTimes();

    // Обработка кликов по карточкам действий
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            console.log('Переход к: ', this.querySelector('h3').textContent);
        });
    });

    // Динамическое обновление статистики (заглушка)
    function updateStats() {
        // В реальном приложении здесь будет AJAX запрос к серверу
        console.log('Обновление статистики...');
    }

    // Обновление статистики каждые 30 секунд
    setInterval(updateStats, 30000);
});