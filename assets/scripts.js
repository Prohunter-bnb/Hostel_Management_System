document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function (event) {
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();

            if (!email || !password) {
                showAlert("Please fill in all fields!", "error");
                event.preventDefault();
            }
        });
    }
});

// Custom alert function
function showAlert(message, type = "success") {
    const alertBox = document.createElement("div");
    alertBox.textContent = message;
    alertBox.style.position = "fixed";
    alertBox.style.top = "20px";
    alertBox.style.left = "50%";
    alertBox.style.transform = "translateX(-50%)";
    alertBox.style.padding = "15px 25px";
    alertBox.style.borderRadius = "5px";
    alertBox.style.fontSize = "18px";
    alertBox.style.zIndex = "1000";
    alertBox.style.backgroundColor = type === "error" ? "#ff4d4d" : "#28a745";
    alertBox.style.color = "#fff";
    document.body.appendChild(alertBox);

    setTimeout(() => {
        alertBox.style.opacity = "0";
        setTimeout(() => document.body.removeChild(alertBox), 500);
    }, 2000);
}
