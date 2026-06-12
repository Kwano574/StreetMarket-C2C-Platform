/* =========================================================
   STREETMARKET JAVASCRIPT
   FILE: script.js
========================================================= */


/* =========================================================
   MOBILE NAVIGATION TOGGLE
========================================================= */

const mobileMenuButton = document.querySelector(".mobile-menu-btn");

const navigation = document.querySelector("nav");

if(mobileMenuButton){

    mobileMenuButton.addEventListener("click", function(){

        navigation.classList.toggle("show-nav");

    });

}


/* =========================================================
   SEARCH FUNCTION
========================================================= */

function searchProducts(){

    const searchInput =
    document.getElementById("searchInput");

    if(searchInput){

        const keyword =
        searchInput.value.trim();

        if(keyword === ""){

            alert("Please enter a product name.");

        }

        else{

            alert(
                "Searching for: " + keyword
            );

        }

    }

}


/* =========================================================
   ADD TO CART
========================================================= */

function addToCart(){

    alert(
        "Product successfully added to cart."
    );

}


/* =========================================================
   REMOVE FROM CART
========================================================= */

function removeCartItem(productName){

    const confirmDelete =
    confirm(
        "Remove " + productName + " from cart?"
    );

    if(confirmDelete){

        alert(
            productName + " removed from cart."
        );

    }

}


/* =========================================================
   QUANTITY INCREASE
========================================================= */

function increaseQuantity(inputId){

    const quantityInput =
    document.getElementById(inputId);

    let currentValue =
    parseInt(quantityInput.value);

    currentValue++;

    quantityInput.value = currentValue;

}


/* =========================================================
   QUANTITY DECREASE
========================================================= */

function decreaseQuantity(inputId){

    const quantityInput =
    document.getElementById(inputId);

    let currentValue =
    parseInt(quantityInput.value);

    if(currentValue > 1){

        currentValue--;

        quantityInput.value = currentValue;

    }

}


/* =========================================================
   CHECKOUT VALIDATION
========================================================= */

function validateCheckout(){

    const fullName =
    document.getElementById("fullName");

    const address =
    document.getElementById("address");

    const phone =
    document.getElementById("phone");

    if(
        fullName.value.trim() === "" ||
        address.value.trim() === "" ||
        phone.value.trim() === ""
    ){

        alert(
            "Please complete all checkout fields."
        );

        return false;

    }

    alert(
        "Order placed successfully."
    );

    return true;

}


/* =========================================================
   LOGIN VALIDATION
========================================================= */

function validateLogin(){

    const email =
    document.getElementById("loginEmail");

    const password =
    document.getElementById("loginPassword");

    if(
        email.value.trim() === "" ||
        password.value.trim() === ""
    ){

        alert(
            "Please enter email and password."
        );

        return false;

    }

    alert(
        "Login successful."
    );

    return true;

}


/* =========================================================
   REGISTER VALIDATION
========================================================= */

function validateRegister(){

    const firstName =
    document.getElementById("firstName");

    const lastName =
    document.getElementById("lastName");

    const email =
    document.getElementById("registerEmail");

    const saID =
    document.getElementById("saID");

    const password =
    document.getElementById("registerPassword");

    const confirmPassword =
    document.getElementById("confirmPassword");

    if(
        firstName.value.trim() === "" ||
        lastName.value.trim() === "" ||
        email.value.trim() === "" ||
        saID.value.trim() === "" ||
        password.value.trim() === "" ||
        confirmPassword.value.trim() === ""
    ){

        alert(
            "Please complete all registration fields."
        );

        return false;

    }

    /* SA ID LENGTH */

    if(saID.value.length !== 13){

        alert(
            "South African ID must contain 13 digits."
        );

        return false;

    }

    /* PASSWORD MATCH */

    if(password.value !== confirmPassword.value){

        alert(
            "Passwords do not match."
        );

        return false;

    }

    alert(
        "Account created successfully."
    );

    return true;

}


/* =========================================================
   DUPLICATE ACCOUNT CHECK
========================================================= */

function checkDuplicateAccount(){

    const saID =
    document.getElementById("saID");

    if(saID){

        const usedIDs = [
            "9901015800087",
            "0305056200089"
        ];

        if(
            usedIDs.includes(saID.value)
        ){

            alert(
                "An account with this South African ID already exists."
            );

            saID.value = "";

        }

    }

}


/* =========================================================
   PRODUCT FILTER
========================================================= */

function filterProducts(category){

    const productCards =
    document.querySelectorAll(".product-card");

    productCards.forEach(function(card){

        if(
            category === "all"
        ){

            card.style.display = "block";

        }

        else{

            const cardCategory =
            card.getAttribute("data-category");

            if(cardCategory === category){

                card.style.display = "block";

            }

            else{

                card.style.display = "none";

            }

        }

    });

}


/* =========================================================
   MESSAGE SENDER
========================================================= */

function sendMessage(){

    const messageInput =
    document.getElementById("messageInput");

    if(
        messageInput.value.trim() === ""
    ){

        alert(
            "Please type a message."
        );

    }

    else{

        alert(
            "Message sent successfully."
        );

        messageInput.value = "";

    }

}


/* =========================================================
   ORDER DELIVERY CONFIRMATION
========================================================= */

function confirmDelivery(orderId){

    const confirmOrder =
    confirm(
        "Confirm delivery for order " + orderId + "?"
    );

    if(confirmOrder){

        alert(
            "Order marked as delivered."
        );

    }

}


/* =========================================================
   PRODUCT APPROVAL
========================================================= */

function approveProduct(productName){

    alert(
        productName + " approved successfully."
    );

}


/* =========================================================
   DELETE PRODUCT
========================================================= */

function deleteProduct(productName){

    const confirmation =
    confirm(
        "Delete " + productName + "?"
    );

    if(confirmation){

        alert(
            productName + " deleted successfully."
        );

    }

}


/* =========================================================
   USER SUSPENSION
========================================================= */

function suspendUser(userName){

    const confirmation =
    confirm(
        "Suspend " + userName + "?"
    );

    if(confirmation){

        alert(
            userName + " suspended successfully."
        );

    }

}


/* =========================================================
   DARK MODE TOGGLE
========================================================= */

function toggleDarkMode(){

    document.body.classList.toggle("dark-mode");

}


/* =========================================================
   ACCESSIBILITY FONT SIZE
========================================================= */

function increaseFontSize(){

    document.body.style.fontSize = "19px";

}


function normalFontSize(){

    document.body.style.fontSize = "16px";

}


/* =========================================================
   SMOOTH SCROLL
========================================================= */

document.querySelectorAll('a[href^="#"]')
.forEach(anchor => {

    anchor.addEventListener("click", function(e){

        e.preventDefault();

        const target =
        document.querySelector(
            this.getAttribute("href")
        );

        if(target){

            target.scrollIntoView({

                behavior: "smooth"

            });

        }

    });

});


/* =========================================================
   PAGE LOADER
========================================================= */

window.addEventListener("load", function(){

    console.log(
        "StreetMarket Loaded Successfully"
    );

});


/* =========================================================
   ACCESSIBILITY ANNOUNCEMENT
========================================================= */

function accessibilityNotice(){

    console.log(
        "StreetMarket accessibility features enabled."
    );

}

accessibilityNotice();


/* =========================================================
   DELIVERY STATUS UPDATE
========================================================= */

function updateDeliveryStatus(status){

    alert(
        "Delivery Status Updated To: " + status
    );

}


/* =========================================================
   SANDBOX PAYMENT DEMO
========================================================= */

function processSandboxPayment(){

    alert(
        "Sandbox payment processed successfully."
    );

}


/* =========================================================
   VIEW CATEGORY
========================================================= */

function openCategory(categoryName){

    alert(
        "Opening " + categoryName + " category."
    );

}


/* =========================================================
   VIEW SELLER LISTINGS
========================================================= */

function openSellerListings(){

    alert(
        "Viewing seller listings."
    );

}


/* =========================================================
   LOGOUT
========================================================= */

function logoutUser(){

    const logout =
    confirm(
        "Are you sure you want to logout?"
    );

    if(logout){

        alert(
            "Logged out successfully."
        );

        window.location.href =
        "homepage.html";

    }

}