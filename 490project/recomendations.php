<?php require_once __DIR__.'/layout.php';site_begin('Trade Recommendations'); ?>
<p class="note">Rule-based educational suggestions, not guaranteed profits or financial advice. No automatic stock orders.</p>

<section class="panel"><button id="reload">Recalculate recommendations</button><div class="scroll" id="recommendations"></div><p id="status"></p></section>


<script src="api.js"></script><script>
async function loadRecommendations() {
    try {
        const suggestions = await rpc('get_recommendations');
        renderTable('recommendations', [
            ['Stock', 'symbol'], ['Action', 'action'],
            ['Suggested shares', 'suggested_quantity'], ['Reason', 'reason']
        ], suggestions.recommendations || []);
        $('status').textContent = suggestions.as_of ? 'Calculated: ' + suggestions.as_of : '';
    } catch (error) {
        showError('status', error);
    }
}

$('reload').addEventListener('click', loadRecommendations);
loadRecommendations();
</script><?php site_end(); ?>
