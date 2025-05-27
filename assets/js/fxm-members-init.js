function share() {
    if ("navigator" in window && window.navigator.share) {
        window.navigator
            .share({
                title: document.title,
                text: document.title,
                url: window.location.href,
            })
            .then(() => console.log("Page was successfully shared"))
            .catch((error) => console.log(error));
    } else {
        console.log("Web Share API not supported");
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('register-button')) {
        document.getElementById('register-button').addEventListener('click', (e) => {
            e.preventDefault();

            document.getElementById('register-button').disabled = true;
            document.getElementById('register-button').value = 'Registering...';

            const newUserEmail = document.getElementById('new-useremail').value;
            const newUserPassword = document.getElementById('new-userpassword').value;
            const newUserPassword2 = document.getElementById('re-pwd').value;
            const newUserFirstName = document.getElementById('new-firstname').value;
            const newUserLastName = document.getElementById('new-lastname').value;

            const newUserAgreement = document.getElementById('fxm_tc_agree').checked ? 1 : 0;

            if (
                newUserEmail.length <= 0 ||
                newUserPassword.length <= 0 ||
                newUserPassword2.length <= 0 ||
                newUserFirstName.length <= 0 ||
                newUserLastName.length <= 0 ||
                newUserAgreement != 1 ||
                newUserPassword !== newUserPassword2
            ) {
                const registerMessage = document.querySelector('.register-message');
                registerMessage.textContent = 'An error has occurred. Please make sure all fields are correctly filled in and passwords match.';
                registerMessage.style.backgroundColor = '#ff6b81';
                registerMessage.style.display = 'block';

                registerMessage.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });

                document.getElementById('register-button').disabled = false;
                document.getElementById('register-button').value = 'Register';

                return;
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', wp4pmAjaxVar.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        const results = xhr.responseText;
                        const registerMessage = document.querySelector('.register-message');
                        registerMessage.innerHTML = results;
                        registerMessage.style.backgroundColor = '#7bed9f';
                        registerMessage.style.display = 'block';

                        registerMessage.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });

                        document.getElementById('register-button').disabled = false;
                        document.getElementById('register-button').value = 'Register';
                    } else {
                        const registerMessage = document.querySelector('.register-message');
                        registerMessage.textContent = 'An error has occurred.';
                        registerMessage.style.backgroundColor = '#ff6b81';
                        registerMessage.style.display = 'block';

                        registerMessage.scrollIntoView({ behavior: 'smooth', block: 'start', inline: 'nearest' });

                        document.getElementById('register-button').disabled = false;
                        document.getElementById('register-button').value = 'Register';
                    }
                }
            };

            let requestString = '';
            requestString += '&new_user_email=' + newUserEmail;
            requestString += '&new_user_password=' + newUserPassword;
            requestString += '&new_firstname=' + newUserFirstName;
            requestString += '&new_lastname=' + newUserLastName;
            requestString += '&loginurl=' + wp4pmAjaxVar.loginurl;

            xhr.send('action=fxm_register_user_front_end' + requestString);
        });
    }

    // Function to update donation goal based on parent campaign
    function updateDonationGoal() {
        const parentSelect = document.getElementById("parent_campaign");
        const goalInput = document.getElementById("donation_goal");
        const startDateInput = document.getElementById("start_date");
        const endDateInput = document.getElementById("end_date");
        const nonceInput = document.getElementById("get_parent_goal_nonce");
        const parentId = parentSelect.value;

        if (parentId) {
            const formData = new FormData();
            formData.append("action", "get_parent_campaign_goal");
            formData.append("parent_id", parentId);
            formData.append("nonce", nonceInput.value);

            fetch(wp4pmAjaxVar.ajaxurl, {
                method: "POST",
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update goal
                        if (data.data.goal) {
                            goalInput.value = data.data.goal;
                            goalInput.readOnly = true;
                            goalInput.title = "This goal is inherited from the parent campaign";
                        } else {
                            goalInput.readOnly = false;
                            goalInput.title = "";
                        }

                        // Update dates
                        if (data.data.start_date) {
                            startDateInput.value = data.data.start_date;
                            startDateInput.readOnly = true;
                            startDateInput.title = "This date is inherited from the parent campaign";
                        } else {
                            startDateInput.readOnly = false;
                            startDateInput.title = "";
                        }

                        if (data.data.end_date) {
                            endDateInput.value = data.data.end_date;
                            endDateInput.readOnly = true;
                            endDateInput.title = "This date is inherited from the parent campaign";
                        } else {
                            endDateInput.readOnly = false;
                            endDateInput.title = "";
                        }
                    }
                })
                .catch(error => console.error("Error:", error));
        } else {
            // Reset fields when no parent is selected
            goalInput.readOnly = false;
            goalInput.title = "";
            startDateInput.readOnly = false;
            startDateInput.title = "";
            endDateInput.readOnly = false;
            endDateInput.title = "";
        }
    }

    // Update goal when parent campaign changes
    const parentSelect = document.getElementById("parent_campaign");
    if (parentSelect) {
        parentSelect.addEventListener("change", updateDonationGoal);
    }



    // Avatar preview
    if (document.querySelector('.avatar-preview img')) {
        const avatarInput = document.querySelector('input[name="avatar"]');
        const avatarPreview = document.querySelector('.avatar-preview img');

        if (avatarInput && avatarPreview) {
            avatarInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        avatarPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    }

    // Campaign image preview
    if (document.getElementById("campaign_image")) {
        const campaignImageInput = document.getElementById("campaign_image");
        if (campaignImageInput) {
            campaignImageInput.addEventListener("change", function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const preview = document.querySelector(".campaign-image-preview img");
                        preview.src = e.target.result;
                        preview.style.display = "block";
                    }
                    reader.readAsDataURL(file);
                }
            });
        }
    }



    /**
     * Tabs
     */
    if (document.querySelectorAll(".whiskey-tabs li a")) {
        var tabLinks = document.querySelectorAll(".whiskey-tabs li a");

        for (var i = 0; i < tabLinks.length; i++) {
            tabLinks[i].onclick = function () {
                var target = this.getAttribute("href").replace("#", "");
                var sections = document.querySelectorAll(".whiskey-tab-content");

                for (var j = 0; j < sections.length; j++) {
                    sections[j].style.display = "none";
                }

                document.getElementById(target).style.display = "block";

                for (var k = 0; k < tabLinks.length; k++) {
                    tabLinks[k].removeAttribute("class");
                }

                this.setAttribute("class", "is-active");

                // Change the URL hash based on the selected tab
                history.pushState(null, null, "#" + target);

                return false;
            }
        };

        // Enable link to tab
        var hash = document.location.hash;
        if (hash && document.querySelector(`.whiskey-tabs li a[href="${hash}"]`)) {
            document.querySelectorAll(`.whiskey-tabs li a[href="${hash}"]`)[0].click();
        }
    }
});
