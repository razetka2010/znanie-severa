// Показать/скрыть пароль
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.querySelector('.toggle-password');
    const passwordInput = document.getElementById('password');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePassword.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                togglePassword.textContent = '👁️';
            }
        });
    }

    // Валидация формы
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const login = document.getElementById('login').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!login) {
                e.preventDefault();
                showError('Введите логин или email');
                document.getElementById('login').focus();
                return false;
            }

            if (!password) {
                e.preventDefault();
                showError('Введите пароль');
                document.getElementById('password').focus();
                return false;
            }
        });
    }

    // Анимация появления
    const loginCard = document.querySelector('.login-card');
    if (loginCard) {
        loginCard.style.opacity = '0';
        loginCard.style.transform = 'translateY(20px)';

        setTimeout(() => {
            loginCard.style.transition = 'all 0.5s ease';
            loginCard.style.opacity = '1';
            loginCard.style.transform = 'translateY(0)';
        }, 100);
    }

    // Автофокус на поле логина
    const loginField = document.getElementById('login');
    if (loginField) {
        setTimeout(() => {
            loginField.focus();
        }, 600);
    }
});

function showError(message) {
    // Удаляем старую ошибку если есть
    const oldError = document.querySelector('.error-message');
    if (oldError) {
        oldError.remove();
    }

    // Создаем новое сообщение об ошибке
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;

    // Вставляем после подзаголовка
    const subtitle = document.querySelector('.subtitle');
    if (subtitle) {
        subtitle.parentNode.insertBefore(errorDiv, subtitle.nextSibling);
    }

    // Анимация появления ошибки
    errorDiv.style.opacity = '0';
    setTimeout(() => {
        errorDiv.style.transition = 'opacity 0.3s ease';
        errorDiv.style.opacity = '1';
    }, 10);
}

// Очистка ошибки при начале ввода
document.addEventListener('input', function(e) {
    if (e.target.id === 'login' || e.target.id === 'password') {
        const errorMessage = document.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.style.opacity = '0';
            setTimeout(() => {
                if (errorMessage.parentNode) {
                    errorMessage.remove();
                }
            }, 300);
        }
    }
});