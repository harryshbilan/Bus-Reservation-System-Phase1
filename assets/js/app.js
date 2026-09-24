// BUSWAY — small progressive-enhancement helpers (each page works without JS too,
// aside from live seat/counter feedback, which requires it by nature).

function centerJourneyDetails() {
  const journeyDetails = document.getElementById('journey-details');
  if (journeyDetails) {
    journeyDetails.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('a[href$="#journey-details"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const targetUrl = new URL(link.href, window.location.href);
      const isCurrentPage = targetUrl.pathname === window.location.pathname;

      if (isCurrentPage) {
        event.preventDefault();
        window.history.replaceState(null, '', '#journey-details');
        centerJourneyDetails();
      }
    });
  });

  if (window.location.hash === '#journey-details') {
    window.requestAnimationFrame(centerJourneyDetails);
  }
});

function adjustPassengers(delta, min, max) {
  const input = document.getElementById('passengers');
  const val = Math.max(min, Math.min(max, parseInt(input.value, 10) + delta));
  input.value = val;
  document.getElementById('passengers-display').textContent = val;
  document.getElementById('dec-btn').disabled = val <= min;
  document.getElementById('inc-btn').disabled = val >= max;
}

function initSeatSelection(maxSeats) {
  const hidden = document.getElementById('selected-seats');
  const btn = document.getElementById('seat-continue');
  const countLabel = document.getElementById('selected-count-label');
  let selected = hidden.value ? hidden.value.split(',').filter(Boolean).map(Number) : [];

  function render() {
    document.querySelectorAll('.seat-btn[data-seat-id]').forEach((el) => {
      const id = parseInt(el.dataset.seatId, 10);
      el.classList.toggle('selected', selected.includes(id));
    });
    hidden.value = selected.join(',');

    if (countLabel) {
      countLabel.textContent = selected.length
        ? selected
            .map((id) => document.querySelector('.seat-btn[data-seat-id="' + id + '"]')?.dataset.seatLabel)
            .filter(Boolean)
            .join(', ')
        : 'None yet';
    }
    if (btn) {
      btn.disabled = selected.length !== maxSeats;
      btn.textContent = selected.length === maxSeats
        ? btn.dataset.readyLabel
        : btn.dataset.remainingTemplate.replace('{n}', maxSeats - selected.length);
    }
  }

  document.querySelectorAll('.seat-btn[data-seat-id]').forEach((el) => {
    el.addEventListener('click', () => {
      if (el.classList.contains('booked')) return;
      const id = parseInt(el.dataset.seatId, 10);
      const idx = selected.indexOf(id);
      if (idx >= 0) {
        selected.splice(idx, 1);
      } else if (selected.length < maxSeats) {
        selected.push(id);
      }
      render();
    });
  });

  render();
}

function formatCardNumber(el) {
  const digits = el.value.replace(/\D/g, '').slice(0, 16);
  el.value = digits.replace(/(.{4})/g, '$1 ').trim();
}
function formatExpiry(el) {
  const d = el.value.replace(/\D/g, '').slice(0, 4);
  el.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
}
function formatCvv(el) {
  el.value = el.value.replace(/\D/g, '').slice(0, 3);
}

function togglePaymentMethod(method) {
  const isCard = method === 'CARD';
  document.getElementById('payment_method').value = method;
  document.getElementById('card-fields').hidden = !isCard;
  document.getElementById('cash-note').hidden = isCard;
  document.querySelectorAll('#card-fields input').forEach((el) => { el.disabled = !isCard; });
  document.getElementById('method-btn-CARD').classList.toggle('active', isCard);
  document.getElementById('method-btn-CASH').classList.toggle('active', !isCard);
  const btn = document.getElementById('pay-btn');
  btn.textContent = isCard ? btn.dataset.cardLabel : btn.dataset.cashLabel;
}

function submitPaymentSpinner() {
  const btn = document.getElementById('pay-btn');
  btn.disabled = true;
  btn.innerHTML =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b91a8" stroke-width="2.5" stroke-linecap="round" class="spinner"><path d="M21 12a9 9 0 1 1-9-9"/></svg> PROCESSING…';
  return true;
}

function openCancelModal() {
  document.getElementById('cancel-modal').classList.add('open');
}
function closeCancelModal() {
  document.getElementById('cancel-modal').classList.remove('open');
}
