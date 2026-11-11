document.addEventListener("DOMContentLoaded", () => {
  const buyerBtn = document.getElementById("buyerBtn");
  const sellerBtn = document.getElementById("sellerBtn");
  const container = document.querySelector(".login-container");
  const serUpload = document.getElementById("serUpload");
  const fileName = document.getElementById("file-name");

  buyerBtn.onclick = () => {
    buyerBtn.classList.add("active");
    sellerBtn.classList.remove("active");
    container.classList.remove("show-seller");
  };

  sellerBtn.onclick = () => {
    sellerBtn.classList.add("active");
    buyerBtn.classList.remove("active");
    container.classList.add("show-seller");
  };

  if (serUpload && fileName) {
    serUpload.addEventListener("change", () => {
      if (serUpload.files.length > 0) {
        fileName.textContent = serUpload.files[0].name;
      } else {
        fileName.textContent = "No file chosen";
      }
    });
  }
});



