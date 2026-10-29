<?php require_once __DIR__.'/layout.php';site_begin('Portfolio History'); ?>

<div class="flex"><label>Display currency<select id="currency"><option>USD</option><option>EUR</option><option>GBP</option></select></label><button id="reload">Reload graph</button></div><p id="status"></p><div id="chart"></div>
</section>


<script src="api.js"></script><scriipt src="charts.js"></script><script>
let portfolioHistory = [];

async function drawHistory() {
    try {
        const displayCurrency = $('currency').value;
        let chartPoints = portfolioHistory;

        if (displayCurrency !== 'USD' && portfolioHistory.length) {
            const fxHistory = await rpc('get_fx_history', {
                base: 'USD',
                quote: displayCurrency,
                from: portfolioHistory[0].date,
                to: portfolioHistory.at(-1).date
            });
            const dailyRates = new Map(
                (fxHistory.rates || []).map(quote => [quote.date, Number(quote.rate)])
            );

            chartPoints = portfolioHistory
                .filter(day => dailyRates.has(day.date))
                .map(day => ({date: day.date, value: Number(day.value) * dailyRates.get(day.date)}));
            $('status').textContent = 'Historical date-matched FX conversion. Missing FX dates are omitted.';
        } else {
            $('status').textContent = 'Historical valuation in USD.';
        }

        lineChart($('chart'), chartPoints, 'date', 'value', `Portfolio ${displayCurrency}`);
    } catch (error) {
        showError('status', error);
    }
}

async function loadHistory() {
    try {
        const reply = await rpc('get_portfolio_history');
        portfolioHistory = (reply.history || [])
            .map(entry => ({
                date: String(entry.date ?? entry.recorded_at ?? '').slice(0, 10),
                value: Number(entry.value_usd ?? entry.total_value_usd ?? entry.value)
            }))
            .sort((first, second) => first.date.localeCompare(second.date));
        await drawHistory();
    } catch (error) {
        showError('status', error);
    }
}

$('currency').addEventListener('change', drawHistory);
$('reload').addEventListener('click', loadHistory);
loadHistory();
</script><?php site_end(); ?>
