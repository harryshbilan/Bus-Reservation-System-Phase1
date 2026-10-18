(() => {
  const swap = document.getElementById('bh-swap');
  const origin = document.getElementById('origin');
  const destination = document.getElementById('destination');
  if (swap && origin && destination) swap.addEventListener('click', () => {
    [origin.value, destination.value] = [destination.value, origin.value];
    origin.focus();
  });
  // No animation or JavaScript is required to submit a search or browse trips.
})();