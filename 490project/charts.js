function lineChart(container, rows, xKey, yKey, label = 'Value') {
    container.replaceChildren();

    const pointsWithValues = (rows || [])
        .map(entry => ({x: String(entry[xKey] ?? ''), y: Number(entry[yKey])}))
        .filter(entry => entry.x && Number.isFinite(entry.y));

    if (!pointsWithValues.length) {
        container.textContent = 'No history available yet.';
        return;
    }

    const svgNamespace = 'http://www.w3.org/2000/svg';
    const chart = document.createElementNS(svgNamespace, 'svg');
    chart.setAttribute('viewBox', '0 0 700 280');
    chart.setAttribute('role', 'img');
    chart.setAttribute('aria-label', label + ' historical line chart');
    chart.classList.add('chart');

    const values = pointsWithValues.map(entry => entry.y);
    const lowest = Math.min(...values);
    const highest = Math.max(...values);
    const range = Math.max(highest - lowest, 1);
    const coordinates = pointsWithValues.map((entry, index) => [
        65 + (pointsWithValues.length === 1 ? 0 : (index / (pointsWithValues.length - 1)) * 610),
        220 - ((entry.y - lowest) / range) * 165
    ]);
    const line = document.createElementNS(svgNamespace, 'polyline');
    line.setAttribute('points', coordinates.map(pair => pair.join(',')).join(' '));
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', '#2b87ee');
    line.setAttribute('stroke-width', '3');
    chart.append(line);

    for (const [value, y] of [[highest, 55], [lowest, 220]]) {
        const marker = document.createElementNS(svgNamespace, 'text');
        marker.setAttribute('x', '8');
        marker.setAttribute('y', String(y));
        marker.setAttribute('font-size', '14');
        marker.setAttribute('fill', 'currentColor');
        marker.textContent = value.toFixed(2);
        chart.append(marker);
    }

    const rangeLabel = document.createElementNS(svgNamespace, 'text');
    rangeLabel.setAttribute('x', '65');
    rangeLabel.setAttribute('y', '260');
    rangeLabel.setAttribute('font-size', '14');
    rangeLabel.setAttribute('fill', 'currentColor');
    rangeLabel.textContent = pointsWithValues[0].x + '  →  ' + pointsWithValues.at(-1).x;
    chart.append(rangeLabel);

    container.append(chart);
}
