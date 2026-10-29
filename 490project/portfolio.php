<?php require_once __DIR__.'/layout.php';site_begin('My Portfolio'); ?>

<section class="panel"><div class="flex"><button id="reload">Refresh portfolio</button><label>Display currency<select id="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>JPY</option></select></label></div>
<div class="grid"><div class="metric">Portfolio value <strong id="total">—</strong></div><div class="metric">Stock holdings <strong id="count">—</strong></div></div><p class="note" id="rateNote"></p></section>



<section class="panel"><h2>Stock holdings</h2>
<div class="scroll" id="holdings"></div>
</section>



<section class="panel"><h2>Virtual currency balances</h2>
<div class="scroll" id="wallets"></div><p><a href="currency.php">Convert currency →</a></p></section>



<p id="error"></p><script src="api.js"></script><script>
let portfolioSnapshot = null;

async function loadPortfolio() {
    try {
        portfolioSnapshot = await rpc('get_portfolio');
        const positions = portfolioSnapshot.holdings || [];
        $('count').textContent = positions.length;

        renderTable('holdings', [
            ['Stock Symbols', 'ticker'], ['Shares', 'shares'], ['Average cost', 'average_buy_price'],
            ['Current Price', 'current_price'], ['Market Value', 'market_value']
        ], positions);
        renderTable('wallets', [
            ['Currency', 'currency'], ['Balance', 'balance']
        ], portfolioSnapshot.wallets || []);

        await convertDisplay();
        $('error').textContent = '';
    } catch (error) {
        showError('error', error);
    }
}

async function convertDisplay() {
    if (!portfolioSnapshot) return;

    const displayCurrency = $('currency').value;
    const portfolioUSD = Number(portfolioSnapshot.total_value_usd ?? portfolioSnapshot.total_value);
    if (!Number.isFinite(portfolioUSD)) {
        throw new Error('VM2 must return total_value_usd in get_portfolio');
    }

    if (displayCurrency === 'USD') {
        $('total').textContent = formatMoney(portfolioUSD);
        $('rateNote').textContent = 'Portfolio base valuation: USD.';
        return;
    }

    try {
        const quote = await rpc('get_fx_rate', {base: 'USD', quote: displayCurrency});
        $('total').textContent = formatMoney(portfolioUSD * Number(quote.rate), displayCurrency);
        $('rateNote').textContent = `USD/${displayCurrency}: ${quote.rate}, observed ${quote.date || 'date unknown'}`;
    } catch (error) {
        showError('rateNote', error);
    }
}

$('currency').addEventListener('change', () => convertDisplay().catch(error => showError('error', error)));
$('reload').addEventListener('click', loadPortfolio);
loadPortfolio();
</script><?php site_end(); ?>
