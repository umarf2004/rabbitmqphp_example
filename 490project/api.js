async function rpc(type, fields = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        throw new Error('Security token missing; please log in again');
    }

    const formBody = new URLSearchParams({type, ...fields});
    const response = await fetch('api.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken
        },
        body: formBody
    });
    const reply = await response.json();

    if (!response.ok || reply.success !== true) {
        throw new Error(reply.message || reply.error || `Backend error (${response.status})`);
    }
    return reply;
}

const $ = id => document.getElementById(id);

function showError(id, error) {
    $(id).textContent = 'Error: ' + error.message;
}

function pretty(id, result) {
    $(id).textContent = JSON.stringify(result, null, 2);
}

function formatMoney(amount, currency = 'USD') {
    const number = Number(amount);
    if (!Number.isFinite(number)) return '—';

    return new Intl.NumberFormat('en-US', {
        style: 'currency', currency
    }).format(number);
}

function td(value) {
    const cell = document.createElement('td');
    cell.textContent = value == null ? '—' : String(value);
    return cell;
}

function renderTable(id, columns, rows) {
    const mount = $(id);
    mount.replaceChildren();

    if (!Array.isArray(rows) || rows.length === 0) {
        mount.textContent = 'No records yet.';
        return;
    }

    const table = document.createElement('table');
    const heading = document.createElement('thead');
    const headerRow = document.createElement('tr');

    columns.forEach(([headingText]) => {
        const cell = document.createElement('th');
        cell.textContent = headingText;
        headerRow.append(cell);
    });
    heading.append(headerRow);
    table.append(heading);

    const tableBody = document.createElement('tbody');
    rows.forEach(record => {
        const tableRow = document.createElement('tr');
        columns.forEach(([, field]) => tableRow.append(td(record[field])));
        tableBody.append(tableRow);
    });
    table.append(tableBody);
    mount.append(table);
}
