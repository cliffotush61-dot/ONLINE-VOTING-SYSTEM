// Example: confirm before voting
function confirmVote() {
    return confirm("Are you sure you want to cast your vote?");
}

// Example: simple validation for registration form
function validateRegistration() {
    let name = document.getElementById("name").value;
    let email = document.getElementById("email").value;
    let password = document.getElementById("password").value;

    if (name === "" || email === "" || password === "") {
        alert("All fields are required!");
        return false;
    }
    return true;
}