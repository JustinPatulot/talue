<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';

$base_url = '/talaue_88_project';

$is_signed_in = isset($_SESSION['dentist_id']);
$dentist_name = 'Account';
$role_id = 0;

if ($is_signed_in) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../controller/connection.php';

    $dentist_id = (int) $_SESSION['dentist_id'];

    $stmt = $conn->prepare("
        SELECT dentist_name, role_id
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $dentist_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($dentist = $result->fetch_assoc()) {
        $dentist_name = $dentist['dentist_name'];
        $role_id = (int) $dentist['role_id'];
    } else {
        $is_signed_in = false;
    }

    $stmt->close();

    if ($is_signed_in) {
        $_SESSION['notification_csrf'] ??= bin2hex(random_bytes(32));

        $notification_csrf_token = $_SESSION['notification_csrf'];
        include __DIR__ . '/account_settings.php';
        include __DIR__ . '/change_password_modal.php';
    }
}
?>

<link rel="stylesheet"
    href="<?= $base_url ?>/styles/navbar.css">

<link rel="stylesheet" href="<?= $base_url ?>/styles/notif_styles.css">


<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container">

        <!-- BRAND -->
        <a class="navbar-brand me-auto" href="<?= $base_url ?>/">
            <img
                src="<?= $base_url ?>/assets/TALAUE88.png"
                alt="Talaue 88 Dental Clinic"
                class="navbar-logo">
        </a>

        <!-- MOBILE ACTIONS: BELL BESIDE HAMBURGER -->
        <div class="navbar-mobile-actions">

            <?php if ($is_signed_in): ?>
                <div class="nav-item dropdown notification-nav">

                    <button
                        type="button"
                        class="notification-bell"
                        id="notificationBell"
                        data-bs-toggle="dropdown"
                        data-bs-auto-close="outside"
                        aria-expanded="false"
                        aria-label="Notifications">

                        <i class="fa-regular fa-bell"
                            aria-hidden="true"></i>

                        <span
                            class="notification-count"
                            id="notificationCount"
                            aria-live="polite"
                            hidden>0</span>
                    </button>

                    <div
                        class="dropdown-menu dropdown-menu-end notification-dropdown"
                        id="notificationDropdown"
                        aria-labelledby="notificationBell">

                        <div class="notification-header">

                            <div class="notification-header-top">
                                <div>
                                    <h6>Notifications</h6>

                                    <div
                                        class="notification-subtitle"
                                        id="notificationSubtitle">
                                        You're all caught up
                                    </div>
                                </div>

                                <div class="notification-header-actions">
                                    <button
                                        type="button"
                                        class="notification-text-button"
                                        id="markAllNotificationsRead">
                                        Mark all read
                                    </button>

                                    <button
                                        type="button"
                                        class="notification-text-button notification-clear-button"
                                        id="clearAllNotifications">
                                        Clear all
                                    </button>
                                </div>
                            </div>

                            <div class="notification-filters">
                                <button
                                    type="button"
                                    class="notification-filter active"
                                    data-notification-filter="all"
                                    aria-pressed="true">
                                    All
                                    <span id="notificationAllCount">0</span>
                                </button>

                                <button
                                    type="button"
                                    class="notification-filter"
                                    data-notification-filter="unread"
                                    aria-pressed="false">
                                    Unread
                                    <span id="notificationUnreadCount">0</span>
                                </button>
                            </div>
                        </div>

                        <div
                            class="notification-list"
                            id="notificationList"
                            aria-live="polite">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- HAMBURGER -->
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNavbar"
                aria-controls="mainNavbar"
                aria-expanded="false"
                aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>
            </button>

        </div>

        <!-- COLLAPSIBLE NAVIGATION -->
        <div class="collapse navbar-collapse" id="mainNavbar">

            <?php if ($is_signed_in): ?>
                <ul class="navbar-nav ms-auto align-items-lg-center">

                    <!-- ACCOUNT DROPDOWN -->
                    <li class="nav-item dropdown">
                        <a
                            class="nav-link dropdown-toggle"
                            href="#"
                            id="accountDropdown"
                            role="button"
                            data-bs-toggle="dropdown"
                            aria-expanded="false">

                            <?= htmlspecialchars(
                                $dentist_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </a>

                        <ul
                            class="dropdown-menu dropdown-menu-end"
                            aria-labelledby="accountDropdown">

                            <li>
                                <button
                                    type="button"
                                    class="dropdown-item"
                                    data-bs-toggle="modal"
                                    data-bs-target="#accountSettingsModal">
                                    Account Settings
                                </button>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="<?= $base_url ?>/controller/signout.php">
                                    Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>

                </ul>
            <?php endif; ?>

        </div>
    </div>
</nav>


<?php if ($is_signed_in): ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';

            const baseUrl = <?= json_encode($base_url) ?>;
            const csrfToken = <?= json_encode($notification_csrf_token) ?>;
            const roleId = <?= (int) $role_id ?>;

            const fetchUrl = baseUrl + '/ajax/notifications.php';
            const actionUrl = baseUrl + '/ajax/notification_action.php';

            // Poll every 6 seconds.
            const POLLING_INTERVAL = 6000;

            const bell = document.getElementById('notificationBell');
            const countBadge = document.getElementById('notificationCount');
            const list = document.getElementById('notificationList');
            const subtitle = document.getElementById('notificationSubtitle');
            const allCount = document.getElementById('notificationAllCount');
            const unreadCount = document.getElementById('notificationUnreadCount');
            const markAllButton = document.getElementById('markAllNotificationsRead');
            const clearAllButton = document.getElementById('clearAllNotifications');

            if (!bell || !list) {
                return;
            }

            const iconMap = {
                booking: 'fa-calendar-check',
                cancel: 'fa-calendar-xmark',
                reschedule: 'fa-calendar-day',
                change: 'fa-user-doctor'
            };

            let notifications = [];
            let counts = {
                all: 0,
                unread: 0
            };
            let activeFilter = 'all';
            let requestInProgress = false;
            let timerId = null;
            let lastFetchFailed = false;

            function escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, function(character) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#039;'
                    } [character];
                });
            }

            function relativeTime(value) {
                if (!value) {
                    return '';
                }

                // MariaDB DATETIME/TIMESTAMP values are returned as SQL strings.
                // Treat the returned value as the browser's local time.
                const normalized = String(value).replace(' ', 'T');
                const date = new Date(normalized);

                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                const seconds = Math.max(
                    0,
                    Math.floor((Date.now() - date.getTime()) / 1000)
                );

                if (seconds < 10) return 'Just now';
                if (seconds < 60) return seconds + ' sec ago';

                const minutes = Math.floor(seconds / 60);
                if (minutes < 60) {
                    return minutes + (minutes === 1 ? ' min ago' : ' mins ago');
                }

                const hours = Math.floor(minutes / 60);
                if (hours < 24) {
                    return hours + (hours === 1 ? ' hr ago' : ' hrs ago');
                }

                const days = Math.floor(hours / 24);
                if (days < 7) {
                    return days + (days === 1 ? ' day ago' : ' days ago');
                }

                return date.toLocaleDateString(undefined, {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }


            function renderNotification(notification) {
                const type = iconMap[notification.type] ?
                    notification.type :
                    'booking';

                const unread = !notification.is_read;
                const notificationId = Number(notification.id);

                const reference = notification.reference_number ?
                    ' · ' + escapeHtml(notification.reference_number) :
                    '';

                return `
        <article
            class="notification-item ${unread ? 'is-unread' : ''}"
            data-notification-id="${notificationId}"
            data-notification-open="${notificationId}"
            role="link"
            tabindex="0"
            aria-label="Open notification: ${escapeHtml(notification.title)}">

            <div class="notification-icon ${type}">
                <i class="fa-solid ${iconMap[type]}"
                   aria-hidden="true"></i>
            </div>

            <div class="notification-content">
                <div class="notification-title">
                    ${escapeHtml(notification.title)}

                    <span
                        class="notification-unread-dot"
                        ${unread ? '' : 'hidden'}
                        aria-label="Unread"></span>
                </div>

                <div class="notification-message">
                    ${escapeHtml(notification.message)}
                </div>

                <div class="notification-meta">
                    <span class="notification-time">
                        ${escapeHtml(relativeTime(notification.created_at))}
                        ${reference}
                    </span>

                    <div class="notification-actions">
                        <button
                            type="button"
                            class="notification-action"
                            data-notification-action="${unread ? 'read' : 'unread'}"
                            data-notification-id="${notificationId}">
                            ${unread ? 'Mark read' : 'Mark unread'}
                        </button>

                        <button
                            type="button"
                            class="notification-action secondary"
                            data-notification-action="delete"
                            data-notification-id="${notificationId}"
                            aria-label="Clear notification: ${escapeHtml(notification.title)}">
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </article>
    `;
            }


            function render() {
                const filtered = notifications.filter(function(notification) {
                    return activeFilter === 'unread' ?
                        !notification.is_read :
                        true;
                });

                countBadge.textContent = counts.unread > 99 ?
                    '99+' :
                    String(counts.unread);

                countBadge.hidden = counts.unread === 0;

                bell.setAttribute(
                    'aria-label',
                    counts.unread > 0 ?
                    'Notifications, ' + counts.unread + ' unread' :
                    'Notifications'
                );

                allCount.textContent = String(counts.all);
                unreadCount.textContent = String(counts.unread);

                subtitle.textContent = counts.unread > 0 ?
                    'You have ' + counts.unread + ' unread notification' +
                    (counts.unread === 1 ? '' : 's') :
                    "You're all caught up";

                markAllButton.disabled = counts.unread === 0;

                if (clearAllButton) {
                    clearAllButton.disabled = counts.all === 0;
                }

                document.querySelectorAll('[data-notification-filter]')
                    .forEach(function(button) {
                        const selected =
                            button.dataset.notificationFilter === activeFilter;

                        button.classList.toggle('active', selected);
                        button.setAttribute('aria-pressed', String(selected));
                    });

                if (filtered.length === 0) {
                    list.innerHTML = `
                <div class="notification-empty">
                    <i class="fa-regular fa-bell-slash"
                       aria-hidden="true"></i>

                    <strong>
                        ${activeFilter === 'unread'
                            ? 'No unread notifications'
                            : 'No notifications yet'}
                    </strong>

                    <span>
                        ${activeFilter === 'unread'
                            ? "You're all caught up."
                            : 'New patient activity will appear here.'}
                    </span>
                </div>
            `;
                    return;
                }

                list.innerHTML = filtered.map(renderNotification).join('');
            }

            async function fetchNotifications() {
                if (requestInProgress || document.hidden) {
                    return;
                }

                requestInProgress = true;

                try {
                    const response = await fetch(fetchUrl, {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (response.status === 401) {
                        stopPolling();
                        window.location.reload();
                        return;
                    }

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to load notifications.');
                    }

                    notifications = Array.isArray(data.notifications) ?
                        data.notifications : [];

                    counts = {
                        all: Number(data.counts?.all ?? 0),
                        unread: Number(data.counts?.unread ?? 0)
                    };

                    render();
                    lastFetchFailed = false;
                } catch (error) {
                    console.error('Notification polling failed:', error);

                    // Preserve the last successfully rendered notifications.
                    if (!lastFetchFailed) {
                        lastFetchFailed = true;
                        subtitle.textContent =
                            'Unable to refresh. Retrying automatically…';
                    }
                } finally {
                    requestInProgress = false;
                }
            }

            async function updateNotification(action, notificationId = null) {
                const formData = new URLSearchParams();

                formData.set('action', action);
                formData.set('csrf_token', csrfToken);

                if (notificationId !== null) {
                    formData.set('notification_id', String(notificationId));
                }

                try {
                    const response = await fetch(actionUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData.toString()
                    });

                    const data = await response.json();

                    if (response.status === 401) {
                        window.location.reload();
                        return false;
                    }

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Unable to update notification.');
                    }

                    await fetchNotifications();
                    return true;
                } catch (error) {
                    console.error('Notification update failed:', error);
                    window.alert(error.message || 'Unable to update notification.');
                    return false;
                }
            }

            function getNotificationDestination(notification) {
                const notificationType = String(
                    notification.notification_type ?? ''
                ).trim().toLowerCase();

                const destinations = {
                    booking_created: {
                        1: '/admin/bookings',
                        2: '/dental-assistant/bookings'
                    },
                    booking_cancelled: {
                        1: '/admin/bookings',
                        2: '/dental-assistant/bookings'
                    },
                    booking_rescheduled: {
                        1: '/admin/bookings',
                        2: '/dental-assistant/bookings'
                    },
                    dentist_change_requested: {
                        1: '/admin/dentist-change-requests'
                    }
                };

                const destination = destinations[notificationType]?.[roleId];

                if (!destination) {
                    return null;
                }

                return baseUrl + destination;
            }

            async function openNotification(notificationId, card) {
                const notification = notifications.find(
                    item => Number(item.id) === Number(notificationId)
                );

                if (!notification) {
                    return;
                }

                const destination = getNotificationDestination(notification);

                if (!destination) {
                    console.warn(
                        'No destination configured for notification type:',
                        notification.notification_type
                    );
                    return;
                }

                if (!notification.is_read) {
                    const success = await updateNotification(
                        'read',
                        notificationId
                    );

                    if (!success) {
                        return;
                    }
                }

                window.location.href = destination;
            }


            list.addEventListener('click', async function(event) {
                // 1. Handle individual notification action buttons first.
                const actionButton = event.target.closest(
                    '[data-notification-action]'
                );

                if (actionButton && list.contains(actionButton)) {
                    event.preventDefault();
                    event.stopPropagation();

                    if (actionButton.disabled) {
                        return;
                    }

                    const action = actionButton.dataset.notificationAction;
                    const notificationId = Number(
                        actionButton.dataset.notificationId
                    );

                    if (
                        !Number.isInteger(notificationId) ||
                        notificationId <= 0
                    ) {
                        console.error('Invalid notification ID.');
                        return;
                    }

                    if (!['read', 'unread', 'delete'].includes(action)) {
                        console.error('Invalid notification action:', action);
                        return;
                    }

                    // Confirm before permanently deleting one notification.
                    if (action === 'delete') {
                        const confirmed = window.confirm(
                            'Clear this notification?\n\n' +
                            'This notification will be permanently deleted.'
                        );

                        if (!confirmed) {
                            return;
                        }
                    }

                    // Prevent repeated clicks while the request is running.
                    actionButton.disabled = true;

                    const originalText = actionButton.textContent;
                    actionButton.textContent = action === 'delete' ?
                        'Clearing…' :
                        'Updating…';

                    const success = await updateNotification(
                        action,
                        notificationId
                    );

                    if (!success) {
                        // Restore the button if the request failed.
                        actionButton.disabled = false;
                        actionButton.textContent = originalText;
                    }

                    return;
                }

                // 2. Otherwise, clicking the notification card opens it.
                const card = event.target.closest(
                    '.notification-item[data-notification-open]'
                );

                if (!card || !list.contains(card)) {
                    return;
                }

                const notificationId = Number(
                    card.dataset.notificationOpen
                );

                if (
                    Number.isInteger(notificationId) &&
                    notificationId > 0
                ) {
                    openNotification(notificationId, card);
                }
            });


            list.addEventListener('keydown', function(event) {
                const card = event.target.closest(
                    '.notification-item[data-notification-open]'
                );

                if (!card || event.target !== card) {
                    return;
                }

                // Support Enter and Space for keyboard users.
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();

                const notificationId = Number(card.dataset.notificationOpen);

                if (Number.isInteger(notificationId) && notificationId > 0) {
                    openNotification(notificationId, card);
                }
            });

            document.querySelectorAll('[data-notification-filter]')
                .forEach(function(button) {
                    button.addEventListener('click', function() {
                        activeFilter = button.dataset.notificationFilter;
                        render();
                    });
                });

            markAllButton.addEventListener('click', async function() {
                if (counts.unread === 0) {
                    return;
                }

                markAllButton.disabled = true;

                const success = await updateNotification('read_all');

                if (!success) {
                    markAllButton.disabled = false;
                }
            });


            if (clearAllButton) {
                clearAllButton.addEventListener('click', async function() {
                    if (counts.all === 0) {
                        return;
                    }

                    const confirmed = window.confirm(
                        'Clear all notifications?\n\n' +
                        'This will permanently delete all your notifications. ' +
                        'This action cannot be undone.'
                    );

                    if (!confirmed) {
                        return;
                    }

                    clearAllButton.disabled = true;

                    const success = await updateNotification('clear_all');

                    if (!success) {
                        clearAllButton.disabled = counts.all === 0;
                    }
                });
            }


            function startPolling() {
                stopPolling();

                fetchNotifications();

                timerId = window.setInterval(function() {
                    fetchNotifications();
                }, POLLING_INTERVAL);
            }

            function stopPolling() {
                if (timerId !== null) {
                    window.clearInterval(timerId);
                    timerId = null;
                }
            }

            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopPolling();
                } else {
                    startPolling();
                }
            });

            // Fetch immediately, then poll every six seconds.
            startPolling();
        });

        if (clearAllButton) {
            clearAllButton.addEventListener('click', async function() {
                if (counts.all === 0) {
                    return;
                }

                const confirmed = window.confirm(
                    'Clear all notifications?\n\n' +
                    'This will permanently delete all your notifications. ' +
                    'This action cannot be undone.'
                );

                if (!confirmed) {
                    return;
                }

                clearAllButton.disabled = true;

                const success = await updateNotification('clear_all');

                if (!success) {
                    clearAllButton.disabled = counts.all === 0;
                }
            });
        }
    </script>

<?php endif; ?>