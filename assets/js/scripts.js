document.addEventListener('DOMContentLoaded', function() {

    // --- LocalStorage Helper Functions ---
    const LS_PREFIX = 'votumNauczycieli_';

    function saveToLocalStorage(key, data) {
        try {
            localStorage.setItem(LS_PREFIX + key, JSON.stringify(data));
        } catch (e) {
            console.error("Error saving to localStorage", e);
        }
    }

    function loadFromLocalStorage(key) {
        try {
            const data = localStorage.getItem(LS_PREFIX + key);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error("Error loading from localStorage", e);
            return null;
        }
    }

    function removeFromLocalStorage(key) {
        try {
            localStorage.removeItem(LS_PREFIX + key);
        } catch (e) {
            console.error("Error removing from localStorage", e);
        }
    }

    // --- Page Specific Logic for LocalStorage ---

    // 1. Login Page (index.php)
    function initLoginPage() {
        const loginForm = document.querySelector('.login-container form');
        if (!loginForm) return;

        const usernameInput = loginForm.querySelector('input[name="username"]');

        // Load
        const savedUsername = loadFromLocalStorage('login_username');
        if (savedUsername && usernameInput) {
            usernameInput.value = savedUsername;
        }

        // Save
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                saveToLocalStorage('login_username', this.value);
            });
        }

        // Clear on successful "login" (simulation)
        loginForm.addEventListener('submit', function() {
            // In a real app, only clear after successful backend validation
            // For demo, we assume submit means success for clearing LS
            // removeFromLocalStorage('login_username'); // Or keep it for convenience
        });
    }

    // 2. Join Session Page (join_session.php)
    function initJoinSessionPage() {
        const joinForm = document.querySelector('.join-session-container form');
        if (!joinForm) return;

        const sessionCodeInput = joinForm.querySelector('input[name="session_code"]');
        const teacherNameInput = joinForm.querySelector('input[name="teacher_name"]');

        // Load
        const savedJoinData = loadFromLocalStorage('join_session_data');
        if (savedJoinData) {
            if (sessionCodeInput && savedJoinData.sessionCode) {
                sessionCodeInput.value = savedJoinData.sessionCode;
            }
            if (teacherNameInput && savedJoinData.teacherName) {
                teacherNameInput.value = savedJoinData.teacherName;
            }
        }

        // Save
        function saveJoinData() {
            const dataToSave = {
                sessionCode: sessionCodeInput ? sessionCodeInput.value : '',
                teacherName: teacherNameInput ? teacherNameInput.value : ''
            };
            saveToLocalStorage('join_session_data', dataToSave);
        }

        if (sessionCodeInput) sessionCodeInput.addEventListener('input', saveJoinData);
        if (teacherNameInput) teacherNameInput.addEventListener('input', saveJoinData);

        // Clear on successful "join" (simulation)
        joinForm.addEventListener('submit', function() {
            // removeFromLocalStorage('join_session_data');
        });
    }

    // 3. Create Session Page (create_session.php)
    const createSessionForm = document.getElementById('create-session-form'); // Add this ID to your form in create_session.php
    const resolutionsListLS = document.getElementById('resolutions-list');
    let resolutionCounterLS = resolutionsListLS ? resolutionsListLS.children.length : 0;

    function saveCreateSessionForm() {
        if (!createSessionForm) return;
        const sessionNameInput = createSessionForm.querySelector('input[name="session_name"]');
        const plannedDateInput = createSessionForm.querySelector('input[name="planned_date"]');
        
        const resolutions = [];
        const resolutionGroups = resolutionsListLS.querySelectorAll('.resolution-input-group');
        resolutionGroups.forEach(group => {
            const nameInput = group.querySelector('input[type="text"]'); // Adjust selector if needed
            const descTextarea = group.querySelector('textarea');
            resolutions.push({
                name: nameInput ? nameInput.value : '',
                description: descTextarea ? descTextarea.value : ''
            });
        });

        const dataToSave = {
            sessionName: sessionNameInput ? sessionNameInput.value : '',
            plannedDate: plannedDateInput ? plannedDateInput.value : '',
            resolutions: resolutions
        };
        saveToLocalStorage('create_session_form_data', dataToSave);
    }

    function loadCreateSessionForm() {
        if (!createSessionForm || !resolutionsListLS) return;

        const savedData = loadFromLocalStorage('create_session_form_data');
        if (!savedData) return;

        const sessionNameInput = createSessionForm.querySelector('input[name="session_name"]');
        const plannedDateInput = createSessionForm.querySelector('input[name="planned_date"]');

        if (sessionNameInput && savedData.sessionName) {
            sessionNameInput.value = savedData.sessionName;
        }
        if (plannedDateInput && savedData.plannedDate) {
            plannedDateInput.value = savedData.plannedDate;
        }

        if (savedData.resolutions && savedData.resolutions.length > 0) {
            resolutionsListLS.innerHTML = ''; // Clear existing static fields if any beyond template
            resolutionCounterLS = 0;
            savedData.resolutions.forEach(resData => {
                resolutionCounterLS++;
                addResolutionField(resData.name, resData.description, false); // false = not from button click
            });
        }
    }
    
    function addResolutionField(name = '', description = '', fromButtonClick = true) {
        if (!resolutionsListLS) return;
        
        const newResolutionDiv = document.createElement('div');
        newResolutionDiv.classList.add('resolution-input-group', 'card', 'mb-3');
        const currentResolutionNumber = resolutionsListLS.children.length + 1;

        newResolutionDiv.innerHTML = `
            <h4>Uchwała #${currentResolutionNumber}</h4>
            <div class="form-group">
                <label for="resolution_name_${currentResolutionNumber}">Nazwa uchwały:</label>
                <input type="text" name="resolutions[${currentResolutionNumber}][name]" id="resolution_name_${currentResolutionNumber}" class="form-control" value="${name}" required>
            </div>
            <div class="form-group">
                <label for="resolution_description_${currentResolutionNumber}">Opis uchwały:</label>
                <textarea name="resolutions[${currentResolutionNumber}][description]" id="resolution_description_${currentResolutionNumber}" class="form-control">${description}</textarea>
            </div>
            <button type="button" class="btn btn-danger btn-sm btn-remove-resolution">Usuń tę uchwałę</button>
        `;
        resolutionsListLS.appendChild(newResolutionDiv);
        updateRemoveButtonsLS();
        if(fromButtonClick) saveCreateSessionForm(); // Save when added by button
    }


    function initCreateSessionPage() {
        if (!createSessionForm) return;
        loadCreateSessionForm(); // Load first

        createSessionForm.addEventListener('input', function(event) {
            // Save on any input change within the form
            if (event.target.closest('.resolution-input-group') || event.target.name === 'session_name' || event.target.name === 'planned_date') {
                saveCreateSessionForm();
            }
        });
        
        const addResolutionButtonLS = document.getElementById('add-resolution-btn');
        if (addResolutionButtonLS) {
            addResolutionButtonLS.addEventListener('click', function() {
                addResolutionField('', '', true); // true = from button click, will trigger save
            });
        }
        updateRemoveButtonsLS(); // Initial call

        createSessionForm.addEventListener('submit', function() {
            // Clear LS after successful submission (simulation)
            // removeFromLocalStorage('create_session_form_data');
        });
    }

    function updateRemoveButtonsLS() {
        if (!resolutionsListLS) return;
        const removeButtons = resolutionsListLS.querySelectorAll('.btn-remove-resolution');
        removeButtons.forEach(button => {
            button.removeEventListener('click', handleRemoveResolutionLS);
            button.addEventListener('click', handleRemoveResolutionLS);
        });
         // Hide remove button if only one resolution exists
        if (resolutionsListLS.children.length <= 1) {
            const firstRemoveBtn = resolutionsListLS.querySelector('.btn-remove-resolution');
            if (firstRemoveBtn) firstRemoveBtn.style.display = 'none';
        } else {
             const allRemoveBtns = resolutionsListLS.querySelectorAll('.btn-remove-resolution');
             allRemoveBtns.forEach(btn => btn.style.display = 'inline-block');
        }
    }

    function handleRemoveResolutionLS(event) {
        if (resolutionsListLS.children.length > 1) {
            event.target.closest('.resolution-input-group').remove();
            // Re-numbering is complex here, backend should handle array indices.
            // For LS, we just save the current state.
            saveCreateSessionForm();
            updateRemoveButtonsLS(); // Update visibility of remove buttons
        } else {
            alert('Musi pozostać przynajmniej jedna uchwała.');
        }
    }

    // 4. Vote Page (vote.php)
    function initVotePage() {
        const voteOptions = document.querySelector('.vote-options');
        if (!voteOptions) return;

        const voteStatusMessage = document.getElementById('vote-status-message');
        const resolutionResultsSummary = document.getElementById('resolution-results-summary');
        const currentResolutionArea = document.getElementById('current-resolution-area');
        const waitingMessage = document.getElementById('waiting-for-resolution');

        // This is tricky as state depends heavily on backend (current resolution ID, session ID)
        // For demonstration, let's save a simple "voted" flag for the UI.
        // A more robust solution would involve session_id and resolution_id in the LS key.
        const lsVoteKey = 'vote_page_last_action';

        // Load
        const lastVoteAction = loadFromLocalStorage(lsVoteKey);
        if (lastVoteAction && lastVoteAction.voted) {
            if(voteStatusMessage) {
                voteStatusMessage.textContent = lastVoteAction.message || `Twój głos został wcześniej zarejestrowany.`;
                voteStatusMessage.className = 'alert alert-success';
                voteStatusMessage.classList.remove('d-none');
            }
            if(voteOptions) voteOptions.querySelectorAll('.btn').forEach(btn => btn.disabled = true);
            if(resolutionResultsSummary && lastVoteAction.resultsVisible) {
                resolutionResultsSummary.innerHTML = `
                    <h4>Wyniki głosowania nad uchwałą:</h4>
                    <p>Za: (zapisane) ${lastVoteAction.results.for || 'Brak danych'}</p>
                    <p>Przeciw: (zapisane) ${lastVoteAction.results.against || 'Brak danych'}</p>
                    <p>Wstrzymano się: (zapisane) ${lastVoteAction.results.abstained || 'Brak danych'}</p>
                `;
                resolutionResultsSummary.classList.remove('d-none');
            }
             if(currentResolutionArea) currentResolutionArea.classList.remove('d-none'); // Assume if voted, resolution was visible
             if(waitingMessage) waitingMessage.classList.add('d-none');

        } else {
             // Simulate director starting a vote (if not restored from LS)
            if (waitingMessage && currentResolutionArea && !currentResolutionArea.classList.contains('d-none')) {
                 // If currentResolutionArea is already visible from HTML, don't override
            } else if (waitingMessage && currentResolutionArea) {
                setTimeout(() => {
                    waitingMessage.classList.add('d-none');
                    currentResolutionArea.classList.remove('d-none');
                }, 1000); // Shorter delay if not restored
            }
        }


        // Save
        if(voteOptions) {
            voteOptions.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('click', function() {
                    const voteType = this.dataset.vote;
                    const message = `Twój głos "${this.textContent}" został zarejestrowany.`;
                    
                    if(voteStatusMessage) {
                        voteStatusMessage.textContent = message;
                        voteStatusMessage.className = 'alert alert-success';
                        voteStatusMessage.classList.remove('d-none');
                    }
                    voteOptions.querySelectorAll('.btn').forEach(btn => btn.disabled = true);

                    // Simulate director ending vote and showing results after a delay
                    setTimeout(() => {
                        const resultsData = { for: 12, against: 3, abstained: 1 }; // Example
                        if (resolutionResultsSummary) {
                            resolutionResultsSummary.innerHTML = `
                                <h4>Wyniki głosowania nad uchwałą:</h4>
                                <p>Za: ${resultsData.for}</p>
                                <p>Przeciw: ${resultsData.against}</p>
                                <p>Wstrzymano się: ${resultsData.abstained}</p>
                                <p class="mt-2">Oczekiwanie na kolejną uchwałę lub zakończenie sesji przez dyrektora...</p>
                            `;
                            resolutionResultsSummary.classList.remove('d-none');
                        }
                        // Save state that vote was cast and results shown
                        saveToLocalStorage(lsVoteKey, { voted: true, message: message, resultsVisible: true, results: resultsData });
                    }, 2000);
                });
            });
        }
         // Consider clearing this LS key when director starts a *new* resolution or session ends.
         // This requires backend interaction or more complex JS logic.
    }


    // --- Original JS for UI interactions (simulations) ---
    // (This part is mostly from your previous `scripts.js` for non-LS specific interactions)

    // Admin Dashboard: Simulate starting a session and getting a code
    const startSessionButton = document.getElementById('start-selected-session-btn');
    const sessionCodeDisplay = document.getElementById('generated-session-code-display');

    if (startSessionButton && sessionCodeDisplay) {
        startSessionButton.addEventListener('click', function() {
            const randomCode = Math.floor(10000000 + Math.random() * 90000000);
            sessionCodeDisplay.innerHTML = `
                <div class="alert alert-success mt-3">
                    Sesja została rozpoczęta! Kod sesji: <strong>${randomCode}</strong><br>
                    Przekaż ten kod nauczycielom, aby mogli dołączyć.
                </div>
            `;
            sessionCodeDisplay.classList.remove('d-none');
            this.disabled = true;
            this.textContent = 'Sesja Aktywna';
            // Potentially clear create_session_form_data here if this was the "submit"
            // removeFromLocalStorage('create_session_form_data');
        });
    }

    // Basic Form Validation Example
    const formsToValidate = document.querySelectorAll('form.needs-validation'); // You might need to add this class to forms
    formsToValidate.forEach(form => {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('[required]');
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid'); // Needs CSS for .is-invalid
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                event.preventDefault();
                alert('Proszę wypełnić wszystkie wymagane pola.');
            }
        });
    });


    // --- Initialize page-specific LS logic ---
    if (document.querySelector('.login-container form')) {
        initLoginPage();
    }
    if (document.getElementById('create-session-form')) { // Ensure form has id="create-session-form"
        initCreateSessionPage();
    }
    if (document.querySelector('.join-session-container form')) {
        initJoinSessionPage();
    }
    if (document.getElementById('current-resolution-area')) { // Vote page
        initVotePage();
    }

});