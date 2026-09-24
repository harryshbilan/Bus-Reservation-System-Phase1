// BUSWAY - little JS helpers for the interactive parts of the site
// (most pages still work fine without JS, except the seat map / passenger counter)
 
function adjustPassengers(delta, min, max) {
  const input = document.getElementById('passengers');
  const val = Math.max(min, Math.min(max, parseInt(input.value, 10) + delta));
 
  input.value = val;
  document.getElementById('passengers-display').textContent = val;
  document.getElementById('dec-btn').disabled = val <= min;
  document.getElementById('inc-btn').disabled = val >= max;
}
 
// eto seat selection man
function initSeatSelection(maxSeats) {
  const hidden = document.getElementById('selected-seats');
  const btn = document.getElementById('seat-continue');
  const countLabel = document.getElementById('selected-count-label');
 
  let selected = [];
  if (hidden.value) {
    selected = hidden.value.split(',').filter(Boolean).map(Number);
  }
 
  function render() {
    const seatButtons = document.querySelectorAll('.seat-btn[data-seat-id]');
    for (const el of seatButtons) {
      const id = parseInt(el.dataset.seatId, 10);
      el.classList.toggle('selected', selected.includes(id));
    }
 
    hidden.value = selected.join(',');
 
    if (countLabel) {
      if (selected.length === 0) {
        countLabel.textContent = 'None yet';
      } else {
        const labels = selected
          .map((id) => document.querySelector('.seat-btn[data-seat-id="' + id + '"]')?.dataset.seatLabel)
          .filter(Boolean);
        countLabel.textContent = labels.join(', ');
      }
    }
 
    if (btn) {
      btn.disabled = selected.length !== maxSeats;
      if (selected.length === maxSeats) {
        btn.textContent = btn.dataset.readyLabel;
      } else {
        btn.textContent = btn.dataset.remainingTemplate.replace('{n}', maxSeats - selected.length);
      }
    }
  }
 
  document.querySelectorAll('.seat-btn[data-seat-id]').forEach((el) => {
    el.addEventListener('click', () => {
      if (el.classList.contains('booked')) return;
 
      const id = parseInt(el.dataset.seatId, 10);
      const idx = selected.indexOf(id);
 
      if (idx >= 0) {
        // already picked, so unpick it
        selected.splice(idx, 1);
      } else if (selected.length < maxSeats) {
        selected.push(id);
      }
 
      render();
    });
  });
 
  render();
}
 
// input formatting para sa payment form
function formatCardNumber(el) {
  const digits = el.value.replace(/\D/g, '').slice(0, 16);
  el.value = digits.replace(/(.{4})/g, '$1 ').trim();
}
 
function formatExpiry(el) {
  const d = el.value.replace(/\D/g, '').slice(0, 4);
  if (d.length > 2) {
    el.value = d.slice(0, 2) + '/' + d.slice(2);
  } else {
    el.value = d;
  }
}
 
function formatCvv(el) {
  el.value = el.value.replace(/\D/g, '').slice(0, 3);
}
 
// mga switches between card / cash sa payment page
function togglePaymentMethod(method) {
  const isCard = method === 'CARD';
 
  document.getElementById('payment_method').value = method;
  document.getElementById('card-fields').hidden = !isCard;
  document.getElementById('cash-note').hidden = isCard;
 
  document.querySelectorAll('#card-fields input').forEach((el) => {
    el.disabled = !isCard;
  });
 
  document.getElementById('method-btn-CARD').classList.toggle('active', isCard);
  document.getElementById('method-btn-CASH').classList.toggle('active', !isCard);
 
  const btn = document.getElementById('pay-btn');
  btn.textContent = isCard ? btn.dataset.cardLabel : btn.dataset.cashLabel;
}
 
// eto spinner habang nag-aantay sa payment "processes"
function submitPaymentSpinner() {
  const btn = document.getElementById('pay-btn');
  btn.disabled = true;
  btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b91a8" stroke-width="2.5" stroke-linecap="round" class="spinner"><path d="M21 12a9 9 0 1 1-9-9"/></svg> PROCESSING...';
  return true;
}
 
function openCancelModal() {
  document.getElementById('cancel-modal').classList.add('open');
}
 
function closeCancelModal() {
  document.getElementById('cancel-modal').classList.remove('open');
}
 