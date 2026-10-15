// Hanapin ang button gamit ang ID
const confirmBtn = document.getElementById("confirmBtn");

// Magdagdag ng event listener
confirmBtn.addEventListener("click", function (event) {
  event.preventDefault(); // para hindi mag-submit agad ang form
  alert("Confirm DOWN wait kalang sa next update!");
  // dito mo puwedeng ilagay ang iba pang logic (e.g. AJAX request, validation, etc.)
});