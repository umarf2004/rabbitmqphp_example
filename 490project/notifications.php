
<?php
require_once __DIR__ . '/layout.php';
site_begin('Email Notifications');
?>

<section class="panel">
    <h2>Email delivery</h2>
    <p class="note">Alerts are sent to the email address on your account. You can still read them on this page.</p>

    <p><strong>Account email:</strong> <span id="accountEmail">Loading…</span></p>
    <p id="emailHint" class="note"></p>

    <form id="emailPrefsForm">
        <label class="email-option">
            <input type="checkbox" id="emailEnabled">
            Email me when something happens
        </label>
        <div class="email-topics">
            <label class="email-option"><input type="checkbox" id="tradeEmails"> Completed stock trades</label>
            <label class="email-option"><input type="checkbox" id="currencyEmails"> Currency rate alerts</label>
            <label class="email-option"><input type="checkbox" id="opportunityEmails"> Market opportunities</label>
        </div>
        <div class="flex">
            <button id="saveEmailPrefs" type="submit">Save email preferences</button>
            <button id="sendTestEmail" type="button" disabled>Send test email</button>
        </div>
    </form>
    <p id="emailStatus" role="status" aria-live="polite"></p>
</section>

<section class="panel">
    <div class="flex">
        <h2>Notification history</h2>
        <button id="reloadNotifications" type="button">Refresh</button>
    </div>
    <p id="historyStatus" aria-live="polite"></p>
    <div id="notificationHistory"></div>
</section>

<style>
.email-option { display: flex; flex-direction: row; align-items: center; gap: .6rem; margin: .6rem 0; }
.email-option input { width: 1.1rem; height: 1.1rem; }
.email-topics { margin: 1rem 0 1.2rem 1.7rem; }
</style>

<script src="api.js"></script>
<script>
const emailFields = [
    ['tradeEmails', 'trade_emails'],
    ['currencyEmails', 'currency_emails'],
    ['opportunityEmails', 'opportunity_emails']
];
let accountHasEmail = false;

function updateEmailForm() {
    const enabled = $('emailEnabled').checked;
    emailFields.forEach(([control]) => {
        $(control).disabled = !enabled || !accountHasEmail;
    });
    $('emailEnabled').disabled = !accountHasEmail;
    $('sendTestEmail').disabled = !accountHasEmail || !enabled;
}

async function readEmailPreferences() {
    const status = $('emailStatus');
    status.textContent = '';
    try {
        const settings = await rpc('get_email_preferences');
        const accountEmail = typeof settings.email === 'string' ? settings.email.trim() : '';
        accountHasEmail = accountEmail.length > 0;
        $('accountEmail').textContent = accountEmail || 'No email address on your account';
        $('emailHint').textContent = accountHasEmail
            ? 'Only your saved account email can receive alerts; changes go through account settings.'
            : 'Add an email address to your account through your account administrator before enabling delivery.';

        $('emailEnabled').checked = settings.enabled === true || settings.enabled === 1 || settings.enabled === '1';
        emailFields.forEach(([control, key]) => {
            $(control).checked = settings[key] === true || settings[key] === 1 || settings[key] === '1';
        });
        updateEmailForm();
    } catch (error) {
        $('emailPrefsForm').hidden = true;
        status.textContent = `Email settings unavailable: ${error.message}. VM2 needs the email preference handlers.`;
    }
}

$('emailEnabled').addEventListener('change', updateEmailForm);
$('emailPrefsForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const saveButton = $('saveEmailPrefs');
    saveButton.disabled = true;
    $('emailStatus').textContent = 'Saving…';

    const preferences = { enabled: $('emailEnabled').checked ? '1' : '0' };
    for (const [control, key] of emailFields) {
        preferences[key] = $(control).checked ? '1' : '0';
    }

    try {
        const result = await rpc('update_email_preferences', preferences);
        $('emailStatus').textContent = result.message || 'Email preferences saved.';
        updateEmailForm();
    } catch (error) {
        showError('emailStatus', error);
    } finally {
        saveButton.disabled = false;
    }
});

$('sendTestEmail').addEventListener('click', async () => {
    const button = $('sendTestEmail');
    button.disabled = true;
    $('emailStatus').textContent = 'Submitting test email…';

    try {
        const result = await rpc('send_test_email');
        $('emailStatus').textContent = result.message || 'Test email request accepted. Check your inbox shortly.';
    } catch (error) {
        showError('emailStatus', error);
    } finally {
        updateEmailForm();
    }
});

async function loadNotifications() {
    const history = $('notificationHistory');
    history.replaceChildren();
    $('historyStatus').textContent = '';

    try {
        const result = await rpc('get_notifications');
        const entries = Array.isArray(result.notifications) ? result.notifications : [];
        if (!entries.length) {
            history.textContent = 'No notifications yet.';
            return;
        }

        for (const note of entries) {
            const item = document.createElement('article');
            item.className = 'panel';

            const message = document.createElement('p');
            message.textContent = String(note.message || 'Notification');
            item.append(message);

            const received = document.createElement('small');
            received.className = 'note';
            received.textContent = note.created_at || '';
            item.append(received);

            if (!note.is_read) {
                const markRead = document.createElement('button');
                markRead.type = 'button';
                markRead.textContent = 'Mark as read';
                markRead.addEventListener('click', async () => {
                    markRead.disabled = true;
                    try {
                        await rpc('mark_notification_read', {notification_id: String(note.id)});
                        await loadNotifications();
                    } catch (error) {
                        showError('historyStatus', error);
                        markRead.disabled = false;
                    }
                });
                item.append(markRead);
            }
            history.append(item);
        }
    } catch (error) {
        showError('historyStatus', error);
    }
}

$('reloadNotifications').addEventListener('click', loadNotifications);
readEmailPreferences();
loadNotifications();
</script>
<?php site_end(); ?>

