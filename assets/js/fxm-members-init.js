function share(e, url = window.location.href) {
    e.preventDefault(); // stops link navigation

    if ("navigator" in window && window.navigator.share) {
        window.navigator
            .share({
                title: document.title,
                text: document.title,
                url: url,
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

    // Helpers to sanitize inherited content from Gutenberg/block editor
    function stripGutenbergComments(html) {
        // Remove Gutenberg block comments like <!-- wp:paragraph --> and <!-- /wp:paragraph -->
        return typeof html === 'string' ? html.replace(/<!--[\s\S]*?-->/g, '') : html;
    }

    function replaceNbsp(str) {
        return typeof str === 'string' ? str.replace(/&nbsp;/g, ' ') : str;
    }

    function decodeEntitiesToText(str) {
        if (typeof str !== 'string') return str;
        const txt = document.createElement('textarea');
        txt.innerHTML = str;
        return txt.value;
    }

    function htmlToPlainText(html) {
        if (typeof html !== 'string') return html;
        const div = document.createElement('div');
        div.innerHTML = html;
        return (div.textContent || div.innerText || '').trim();
    }

    // Function to update donation goal and inherit fields based on parent campaign
    function updateDonationGoal() {
        const parentSelect = document.getElementById("parent_campaign");
        const goalInput = document.getElementById("donation_goal");
        const startDateInput = document.getElementById("start_date");
        const endDateInput = document.getElementById("end_date");
        const nonceInput = document.getElementById("get_parent_goal_nonce");
        // Additional fields to auto-populate
        const titleInput = document.getElementById("campaign_title");
        const summaryTextarea = document.getElementById("short_description");
        const descriptionTextarea = document.getElementById("long_description");
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

                        // Update title, summary, description from parent campaign (leave editable)
                        if (titleInput && data.data.title) {
                            titleInput.value = data.data.title;
                        }
                        if (summaryTextarea && data.data.excerpt) {
                            summaryTextarea.value = data.data.excerpt;
                        }
                        if (descriptionTextarea && data.data.content) {
                            // Convert to plain text: strip Gutenberg comments, normalize spaces, remove all HTML
                            const cleaned = replaceNbsp(stripGutenbergComments(data.data.content));
                            descriptionTextarea.value = htmlToPlainText(cleaned);
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

            // Leave title/summary/description untouched when clearing parent selection
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

    // Handle variation forms
    const variationForms = document.querySelectorAll('.campaign-donation .variations_form');

    variationForms.forEach(form => {
        const selects = form.querySelectorAll('.variations select');
        const submitButton = form.querySelector('.single_add_to_cart_button');
        const variationContainer = form.querySelector('.single_variation');
        const variationIdInput = form.querySelector('.variation_id');

        if (selects.length === 0) return;

        // Function to check if all variations are selected
        function checkVariationsSelected() {
            let allSelected = true;
            selects.forEach(select => {
                if (!select.value) {
                    allSelected = false;
                }
            });

            if (submitButton) {
                submitButton.disabled = !allSelected;
            }

            return allSelected;
        }

        // Function to find matching variation
        function findMatchingVariation() {
            const attributes = {};
            let hasCustom = false;

            selects.forEach(select => {
                const attributeName = select.getAttribute('data-attribute_name');
                const value = select.value;
                attributes[attributeName] = value;

                // Check if any selection is "Custom"
                if (value.toLowerCase() === 'custom') {
                    hasCustom = true;
                }
            });

            // Get product variations from the form data attribute
            const variations = form.getAttribute('data-product_variations');
            const nypActive = form.getAttribute('data-nyp_active') === '1';

            if (!variations) return null;

            try {
                const variationsData = JSON.parse(variations);

                // Find matching variation
                for (let i = 0; i < variationsData.length; i++) {
                    const variation = variationsData[i];
                    let match = true;

                    for (const attr in attributes) {
                        if (variation.attributes[attr] !== attributes[attr] && variation.attributes[attr] !== '') {
                            match = false;
                            break;
                        }
                    }

                    if (match) {
                        return {
                            ...variation,
                            has_custom: hasCustom,
                            nyp_active: nypActive
                        };
                    }
                }
            } catch (e) {
                console.log('Error parsing variations data:', e);
            }

            return {
                variation_id: 0,
                display_price: '',
                display_regular_price: '',
                variation_description: '',
                has_custom: hasCustom,
                nyp_active: nypActive
            };
        }

        // Function to update variation display
        function updateVariationDisplay() {
            if (!checkVariationsSelected()) {
                if (variationContainer) {
                    variationContainer.innerHTML = '';
                    variationContainer.style.display = 'none';
                }
                if (variationIdInput) {
                    variationIdInput.value = '0';
                }
                return;
            }

            const variation = findMatchingVariation();
            if (variation && variationContainer) {
                let html = '';

                if (variation.display_price && !variation.has_custom) {
                    html += `<div class="woocommerce-variation-price">Donation Amount: ${variation.display_price}</div>`;
                }

                if (variation.variation_description) {
                    html += `<div class="woocommerce-variation-description">${variation.variation_description}</div>`;
                }

                // Show custom price input if "Custom" is selected
                if (variation.has_custom) {
                    html += `
                        <div class="custom-price-field">
                            <label for="custom_price_${form.getAttribute('data-product_id')}">Enter your donation amount:</label>
                            <div class="custom-price-input-wrapper">
                                <input 
                                    type="number" 
                                    id="custom_price_${form.getAttribute('data-product_id')}" 
                                    name="nyp" 
                                    class="custom-price-input" 
                                    placeholder="0.00" 
                                    min="0.01" 
                                    step="0.01"
                                    required
                                />
                            </div>
                            <small class="custom-price-note">Please enter the amount you would like to donate.</small>
                        </div>
                    `;
                }

                variationContainer.innerHTML = html;
                variationContainer.style.display = 'block';

                if (variationIdInput) {
                    variationIdInput.value = variation.variation_id || '0';
                }

                // Add event listener to custom price input if it exists
                if (variation.has_custom) {
                    const customPriceInput = variationContainer.querySelector('.custom-price-input');
                    if (customPriceInput) {
                        customPriceInput.addEventListener('input', function () {
                            // Enable/disable submit button based on custom price input
                            const hasValue = this.value && parseFloat(this.value) > 0;
                            if (submitButton) {
                                submitButton.disabled = !hasValue;
                            }
                        });

                        // Initial check for submit button state
                        const hasValue = customPriceInput.value && parseFloat(customPriceInput.value) > 0;
                        if (submitButton) {
                            submitButton.disabled = !hasValue;
                        }
                    }
                }
            }
        }

        // Add event listeners to all select elements
        selects.forEach(select => {
            select.addEventListener('change', () => {
                updateVariationDisplay();
            });
        });

        // Initial check
        checkVariationsSelected();
    });
});
