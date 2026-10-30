<?php require_once __DIR__.'/layout.php';site_begin('Currency Rate Alerts'); ?>
<section class="panel"><h2>Create rate alert</h2>
<form id="form" class="flex">
<label>Base<select id="base"><option>USD</option><option>EUR</option><option>GBP</option></select></label>
<label>Target currency<select id="quote"><option>EUR</option><option>USD</option><option>GBP</option></select></label>
<label>Condition<select id="comparison"><option value="above">At or above</option><option value="below">At or below</option></select></label>
<label>Target rate<input type="number" id="target_rate" step="0.00000001" min="0.00000001" required></label><button>Save alert</button>
</form>
<p id="status" aria-live="polite"></p></section>



<section class="panel"><h2>Saved alerts</h2>
<div id="alerts" class="scroll"></div>
</section>


<script src="api.js"></script><script>
async function loadCurrencyAlerts() {
    try {
        const reply = await rpc('get_currency_alerts');
        renderTable('alerts', [
            ['Base', 'base_currency'], ['Target', 'target_currency'],
            ['Condition', 'condition_type'], ['Rate', 'target_rate'],
            ['Active', 'is_active']
        ], reply.alerts || []);
    } catch (error) {
        showError('alerts', error);
    }
}

$('form').addEventListener('submit', async event => {
    event.preventDefault();
    const base_currency = $('base').value;
    const target_currency = $('quote').value;
    const condition_type = $('comparison').value;
    const target_rate = $('target_rate').value;

    if (base_currency === target_currency) {
        $('status').textContent = 'Pick two different currencies.';
        return;
    }

    try {
        const savedAlert = await rpc('create_currency_alert', {
            base_currency, target_currency, condition_type, target_rate
        });
        $('status').textContent = savedAlert.message || 'Alert created';
        await loadCurrencyAlerts();
    } catch (error) {
        showError('status', error);
    }
});

loadCurrencyAlerts();
</script><?php site_end(); ?>
