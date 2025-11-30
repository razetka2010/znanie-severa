// Скрипт для управления учебными периодами
class AcademicPeriodsManager {
    constructor() {
        this.init();
    }

    init() {
        console.log('Academic Periods Manager initialized');
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Фильтры
        const filterForm = document.getElementById('period-filters');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => this.handleFilter(e));
        }

        // Кнопка сброса фильтров
        const resetFiltersBtn = document.getElementById('reset-filters');
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener('click', () => this.resetFilters());
        }

        // Обработчики для действий в таблице
        this.bindTableActions();
    }

    bindTableActions() {
        // Редактирование
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const periodId = e.target.closest('tr').dataset.id;
                this.editPeriod(periodId);
            });
        });

        // Просмотр
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const periodId = e.target.closest('tr').dataset.id;
                this.viewPeriod(periodId);
            });
        });

        // Установка текущего периода
        document.querySelectorAll('.btn-current').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const periodId = e.target.closest('tr').dataset.id;
                this.setCurrentPeriod(periodId);
            });
        });

        // Удаление
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const periodId = e.target.closest('tr').dataset.id;
                this.confirmDelete(periodId);
            });
        });
    }

    loadInitialData() {
        // Загрузка начальных данных, если нужно
        console.log('Loading initial academic periods data...');
    }

    editPeriod(id) {
        window.location.href = `academic_periods.php?action=edit&id=${id}`;
    }

    viewPeriod(id) {
        window.location.href = `academic_periods.php?action=view&id=${id}`;
    }

    confirmDelete(id) {
        if (confirm('Вы уверены, что хотите удалить этот учебный период? Это действие нельзя отменить.')) {
            this.deletePeriod(id);
        }
    }

    deletePeriod(id) {
        // Показываем индикатор загрузки
        const deleteBtn = document.querySelector(`.btn-delete[onclick*="${id}"]`);
        if (deleteBtn) {
            const originalHTML = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '⏳';
            deleteBtn.disabled = true;
        }

        // Отправляем запрос на удаление
        fetch(`academic_periods.php?action=delete&id=${id}`, {
            method: 'GET'
        })
            .then(response => {
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.text();
            })
            .then(data => {
                // Восстанавливаем кнопку
                if (deleteBtn) {
                    deleteBtn.innerHTML = '🗑️';
                    deleteBtn.disabled = false;
                }

                if (data && data.trim().startsWith('<!DOCTYPE')) {
                    console.error('Received HTML instead of expected response');
                    this.showNotification('Ошибка при удалении. Проверьте авторизацию.', 'error');
                    return;
                }

                try {
                    const result = JSON.parse(data);
                    if (result.success) {
                        this.showNotification('Учебный период удален', 'success');
                        const row = document.querySelector(`tr[data-id="${id}"]`);
                        if (row) {
                            row.style.opacity = '0.5';
                            setTimeout(() => row.remove(), 500);
                        }
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showNotification('Ошибка при удалении: ' + (result.message || 'Неизвестная ошибка'), 'error');
                    }
                } catch (e) {
                    console.log('Non-JSON response, assuming success and reloading');
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);

                if (deleteBtn) {
                    deleteBtn.innerHTML = '🗑️';
                    deleteBtn.disabled = false;
                }

                this.showNotification('Ошибка при удалении: ' + error.message, 'error');
            });
    }

    setCurrentPeriod(id) {
        if (confirm('Установить этот период как текущий для школы?')) {
            // Показываем индикатор загрузки
            const currentBtn = document.querySelector(`.btn-current[onclick*="${id}"]`);
            if (currentBtn) {
                const originalHTML = currentBtn.innerHTML;
                currentBtn.innerHTML = '⏳';
                currentBtn.disabled = true;
            }

            fetch(`academic_periods.php?action=set_current&id=${id}`, {
                method: 'GET'
            })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                        return;
                    }
                    return response.text();
                })
                .then(data => {
                    if (currentBtn) {
                        currentBtn.innerHTML = '⭐';
                        currentBtn.disabled = false;
                    }

                    if (data && data.trim().startsWith('<!DOCTYPE')) {
                        this.showNotification('Ошибка при установке текущего периода. Проверьте авторизацию.', 'error');
                        return;
                    }

                    try {
                        const result = JSON.parse(data);
                        if (result.success) {
                            this.showNotification('Текущий период установлен', 'success');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            this.showNotification('Ошибка: ' + (result.message || 'Неизвестная ошибка'), 'error');
                        }
                    } catch (e) {
                        console.log('Non-JSON response, assuming success and reloading');
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);

                    if (currentBtn) {
                        currentBtn.innerHTML = '⭐';
                        currentBtn.disabled = false;
                    }

                    this.showNotification('Ошибка при установке текущего периода: ' + error.message, 'error');
                });
        }
    }

    handleFilter(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const filters = Object.fromEntries(formData);

        console.log('Applying filters:', filters);
        this.applyFilters(filters);
    }

    applyFilters(filters) {
        const rows = document.querySelectorAll('.period-data-table tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            if (row.classList.contains('empty-state')) {
                return;
            }

            let showRow = true;

            // Фильтр по школе
            if (filters.school && filters.school !== 'all') {
                const schoolId = row.dataset.schoolId;
                if (schoolId !== filters.school) {
                    showRow = false;
                }
            }

            // Фильтр по статусу
            if (filters.status && filters.status !== 'all') {
                const statusElement = row.querySelector('.status-badge');
                if (statusElement) {
                    const status = statusElement.textContent.trim().toLowerCase();
                    const statusMap = {
                        'текущий': 'current',
                        'архивный': 'archived'
                    };
                    if (statusMap[status] !== filters.status) {
                        showRow = false;
                    }
                }
            }

            // Фильтр по году
            if (filters.year && filters.year !== 'all') {
                const year = row.dataset.year;
                if (year !== filters.year) {
                    showRow = false;
                }
            }

            row.style.display = showRow ? '' : 'none';
            if (showRow) visibleCount++;
        });

        this.showNoResultsMessage(visibleCount === 0);
    }

    showNoResultsMessage(show) {
        let noResultsRow = document.getElementById('no-results-message');

        if (show && !noResultsRow) {
            noResultsRow = document.createElement('tr');
            noResultsRow.id = 'no-results-message';
            noResultsRow.innerHTML = `
                <td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <div style="font-size: 48px; margin-bottom: 10px;">🔍</div>
                    <h3 style="margin-bottom: 10px;">Ничего не найдено</h3>
                    <p>Попробуйте изменить параметры фильтрации</p>
                </td>
            `;
            document.querySelector('.period-data-table tbody').appendChild(noResultsRow);
        } else if (!show && noResultsRow) {
            noResultsRow.remove();
        }
    }

    resetFilters() {
        const filterForm = document.getElementById('period-filters');
        if (filterForm) {
            filterForm.reset();
            this.applyFilters({});
        }
    }

    validatePeriodForm() {
        const form = document.getElementById('period-form');
        if (!form) return true;

        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        // Очищаем предыдущие ошибки
        this.clearAllFieldErrors();

        // Проверка обязательных полей
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.showFieldError(field, 'Это поле обязательно для заполнения');
                isValid = false;
            }
        });

        // Проверка дат
        const startDate = form.querySelector('input[name="start_date"]');
        const endDate = form.querySelector('input[name="end_date"]');

        if (startDate && endDate && startDate.value && endDate.value) {
            const start = new Date(startDate.value);
            const end = new Date(endDate.value);

            if (end <= start) {
                this.showFieldError(endDate, 'Дата окончания должна быть позже даты начала');
                isValid = false;
            }

            // Проверка что период не слишком длинный (максимум 2 года)
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const maxDays = 730; // 2 года

            if (diffDays > maxDays) {
                this.showFieldError(endDate, 'Период не может быть длиннее 2 лет');
                isValid = false;
            }

            // Проверка что период не в прошлом (можно начать максимум за 1 месяц до текущей даты)
            const today = new Date();
            const minStartDate = new Date(today);
            minStartDate.setMonth(today.getMonth() - 1);

            if (start < minStartDate) {
                this.showFieldError(startDate, 'Период не может начинаться более чем за 1 месяц до текущей даты');
                isValid = false;
            }
        }

        if (!isValid) {
            this.showNotification('Исправьте ошибки в форме', 'error');
            const firstError = form.querySelector('.field-error');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        return isValid;
    }

    showFieldError(field, message) {
        this.clearFieldError(field);

        field.style.borderColor = '#dc3545';
        field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.1)';

        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.style.cssText = `
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            font-weight: 500;
        `;
        errorDiv.textContent = message;

        field.parentNode.appendChild(errorDiv);
    }

    clearFieldError(field) {
        field.style.borderColor = '';
        field.style.boxShadow = '';

        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    clearAllFieldErrors() {
        const errorFields = document.querySelectorAll('.field-error');
        errorFields.forEach(error => error.remove());

        const fields = document.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.style.borderColor = '';
            field.style.boxShadow = '';
        });
    }

    showNotification(message, type = 'info') {
        // Удаляем существующие уведомления
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());

        // Создаем уведомление
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        const styles = {
            position: 'fixed',
            top: '20px',
            right: '20px',
            padding: '15px 20px',
            borderRadius: '8px',
            color: 'white',
            fontWeight: '600',
            zIndex: '10000',
            boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            maxWidth: '400px',
            animation: 'slideInRight 0.3s ease-out'
        };

        Object.assign(notification.style, styles);

        if (type === 'success') {
            notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
        } else if (type === 'error') {
            notification.style.background = 'linear-gradient(135deg, #dc3545, #e83e8c)';
        } else {
            notification.style.background = 'linear-gradient(135deg, #17a2b8, #6f42c1)';
        }

        notification.textContent = message;
        document.body.appendChild(notification);

        // Добавляем CSS анимацию если её нет
        if (!document.querySelector('#notification-styles')) {
            const style = document.createElement('style');
            style.id = 'notification-styles';
            style.textContent = `
                @keyframes slideInRight {
                    from {
                        opacity: 0;
                        transform: translateX(100%);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
                @keyframes slideOutRight {
                    from {
                        opacity: 1;
                        transform: translateX(0);
                    }
                    to {
                        opacity: 0;
                        transform: translateX(100%);
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Удаляем уведомление через 4 секунды
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 4000);
    }

    // Автоматическое заполнение дат на основе названия периода
    setupDateAutoFill() {
        const nameInput = document.querySelector('input[name="name"]');
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');

        if (nameInput && startDateInput && endDateInput) {
            nameInput.addEventListener('blur', () => {
                const name = nameInput.value.trim();

                // Пытаемся извлечь год из названия
                const yearMatch = name.match(/(\d{4})[-–](\d{4})/);
                if (yearMatch) {
                    const startYear = parseInt(yearMatch[1]);
                    const endYear = parseInt(yearMatch[2]);

                    // Устанавливаем стандартные даты учебного года
                    if (!startDateInput.value) {
                        startDateInput.value = `${startYear}-09-01`;
                    }
                    if (!endDateInput.value) {
                        endDateInput.value = `${endYear}-05-31`;
                    }
                }
            });
        }
    }
}

// Инициализация менеджера учебных периодов
const academicPeriodsManager = new AcademicPeriodsManager();

// Глобальные функции для использования в HTML
function validatePeriodForm() {
    return academicPeriodsManager.validatePeriodForm();
}

function editPeriod(id) {
    academicPeriodsManager.editPeriod(id);
}

function viewPeriod(id) {
    academicPeriodsManager.viewPeriod(id);
}

function confirmDelete(id) {
    academicPeriodsManager.confirmDelete(id);
}

function setCurrentPeriod(id) {
    academicPeriodsManager.setCurrentPeriod(id);
}

function resetFilters() {
    academicPeriodsManager.resetFilters();
}

// Обработчики для страницы
document.addEventListener('DOMContentLoaded', function() {
    // Автоматическое применение фильтров при изменении
    const filterInputs = document.querySelectorAll('#period-filters input, #period-filters select');
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            const form = document.getElementById('period-filters');
            if (form) {
                const formData = new FormData(form);
                const filters = Object.fromEntries(formData);
                academicPeriodsManager.applyFilters(filters);
            }
        });
    });

    // Подтверждение формы
    const periodForm = document.getElementById('period-form');
    if (periodForm) {
        periodForm.addEventListener('submit', function(e) {
            if (!validatePeriodForm()) {
                e.preventDefault();
            }
        });
    }

    // Настройка автозаполнения дат
    academicPeriodsManager.setupDateAutoFill();

    // Установка минимальных и максимальных дат
    const startDateInput = document.querySelector('input[name="start_date"]');
    const endDateInput = document.querySelector('input[name="end_date"]');

    if (startDateInput && endDateInput) {
        const today = new Date();
        const maxDate = new Date(today);
        maxDate.setFullYear(today.getFullYear() + 2);

        // Устанавливаем минимальную дату (1 год назад)
        const minDate = new Date(today);
        minDate.setFullYear(today.getFullYear() - 1);

        startDateInput.min = minDate.toISOString().split('T')[0];
        startDateInput.max = maxDate.toISOString().split('T')[0];
        endDateInput.min = minDate.toISOString().split('T')[0];
        endDateInput.max = maxDate.toISOString().split('T')[0];

        // Обновление минимальной даты окончания при изменении даты начала
        startDateInput.addEventListener('change', function() {
            if (this.value) {
                const minEndDate = new Date(this.value);
                minEndDate.setDate(minEndDate.getDate() + 1);
                endDateInput.min = minEndDate.toISOString().split('T')[0];

                // Если дата окончания раньше новой минимальной даты, сбрасываем её
                if (endDateInput.value && new Date(endDateInput.value) < minEndDate) {
                    endDateInput.value = '';
                }
            }
        });
    }
});

// Глобальные обработчики ошибок
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
});
