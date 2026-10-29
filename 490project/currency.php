<?php
require_once __DIR__ . '/layout.php';
require_once('/home/umarf/git/rabbitmqphp_example/rabbitMQLib.inc');
require_once('/home/umarf/git/rabbitmqphp_example/get_host_info.inc');
site_begin('Currency Wallet');

?>

<section class="panel">
    <h2>My Currency Wallet</h2>

    <p>View your available virtual funds.</p>

    <div id="walletBalances">
        Loading wallet balances...
    </div>
    <br>
    <button type="button" id="refillButton">
    Refill Virtual Funds
    </button>
    <p id="refillMessage"></p>


</section>

<section class="panel">
    <h2>Convert Currency</h2>

    <div class="flex">
        <label>
            From Currency
            <select id="fromCurrency">
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
                <option value="GBP">GBP</option>
            </select>
        </label>

        <label>
            To Currency
            <select id="toCurrency">
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
                <option value="GBP">GBP</option>
            </select>
        </label>

        <label>
            Amount
            <input
                id="convertAmount"
                type="number"
                min="0.01"
                step="0.01"
                placeholder="Enter amount"
            >
        </label>
    </div>
    <br>
    <button type="button" id="convertButton">
        Convert Currency
    </button>

    <p id="convertMessage"></p>
</section>

<script src="api.js"></script>

<script>
async function loadWallets() {

    try {
        const response = await rpc('get_wallets');

        const wallets = response.wallets || [];

        if (wallets.length === 0) {
            $('walletBalances').textContent =
                'No wallets found for this account.';
            return;
        }

        const formattedWallets = wallets.map(wallet => ({
            currency: wallet.currency,
            balance: formatMoney(wallet.balance, wallet.currency)
        }));

        renderTable(
            'walletBalances',
            [
                ['Currency', 'currency'],
                ['Balance', 'balance']
            ],
            formattedWallets
        );

    } catch (error) {
        showError('walletBalances', error);
    }
}

$('refillButton').addEventListener('click', async function () {

    $('refillButton').disabled = true;
    $('refillMessage').textContent = 'Refilling virtual funds...';

    try {
        const response = await rpc('add_virtual_funds');

        $('refillMessage').textContent =
            response.message || 'Virtual funds updated successfully.';

        await loadWallets();

    } catch (error) {
        showError('refillMessage', error);

    } finally {
        $('refillButton').disabled = false;
    }

});


$('convertButton').addEventListener('click', async function () {

    const fromCurrency = $('fromCurrency').value;
    const toCurrency = $('toCurrency').value;
    const amount = $('convertAmount').value;

    // Make sure the currencies are different
    if (fromCurrency === toCurrency) {
        $('convertMessage').textContent =
            'Please select two different currencies.';
        return;
    }

    // Validate the amount
    if (!amount || !Number.isFinite(Number(amount)) || Number(amount) <= 0) {
        $('convertMessage').textContent =
            'Please enter a valid amount.';
        return;
    }

    $('convertButton').disabled = true;
    $('convertMessage').textContent = 'Converting currency...';

    try {
        const response = await rpc('convert_currency', {
            from_currency: fromCurrency,
            to_currency: toCurrency,
            amount: amount
        });

        $('convertMessage').textContent =
            response.message || 'Currency converted successfully!';

        // Refresh wallet balances
        await loadWallets();

    } catch (error) {
        showError('convertMessage', error);

    } finally {
        $('convertButton').disabled = false;
    }

});

loadWallets();
</script>

<?php site_end(); ?>