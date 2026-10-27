<?php require_once __DIR__.'/layout.php';site_begin('Notifications'); ?>

<section class="panel"><button id="reload">Refresh messages</button><p class="note">This page displays stored notifications. A phone/text delivery worker is also needed if required by your instructor.</p><p id="status"></p><div id="list"></div>
</section>


<script src="api.js"></script><script>
async function loadNotifications() {
    const messageList = $('list');
    messageList.replaceChildren();

    try {
        const reply = await rpc('get_notifications');
        if (!(reply.notifications || []).length) {
            messageList.textContent = 'No notifications yet.';
            return;
        }

        for (const notification of reply.notifications) {
            const card = document.createElement('article');
            card.className = 'panel';

            const message = document.createElement('p');
            message.textContent = `${notification.message || ''} — ${notification.created_at || ''}`;
            card.append(message);

            if (!notification.is_read) {
                const markRead = document.createElement('button');
                markRead.textContent = 'Mark as read';
                markRead.addEventListener('click', async () => {
                    try {
                        await rpc('mark_notification_read', {notification_id: String(notification.id)});
                        loadNotifications();
                    } catch (error) {
                        showError('status', error);
                    }
                });
                card.append(markRead);
            }

            messageList.append(card);
        }
        $('status').textContent = '';
    } catch (error) {
        showError('status', error);
    }
}

$('reload').addEventListener('click', loadNotifications);
loadNotifications();
</script><?php site_end(); ?>
