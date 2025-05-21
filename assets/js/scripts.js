document.addEventListener('DOMContentLoaded', function() {

    // --- Create Session Page: Dynamic Resolution Fields ---
    const addResolutionButton = document.getElementById('add-resolution-btn');
    const resolutionsList = document.getElementById('resolutions-list');
    let resolutionCounter = resolutionsList ? resolutionsList.children.length : 0;

    if (addResolutionButton && resolutionsList) {
        addResolutionButton.addEventListener('click', function() {
            resolutionCounter++;
            const newResolutionDiv = document.createElement('div');
            newResolutionDiv.classList.add('resolution-input-group', 'card', 'mb-3');
            newResolutionDiv.innerHTML = `
                <h4>Uchwała #${resolutionCounter}</h4>
                <div class="form-group">
                    <label for="resolution_name_${resolutionCounter}">Nazwa uchwały:</label>
                    <input type="text" name="resolutions[${resolutionCounter}][name]" id="resolution_name_${resolutionCounter}" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="resolution_description_${resolutionCounter}">Opis uchwały:</label>
                    <textarea name="resolutions[${resolutionCounter}][description]" id="resolution_description_${resolutionCounter}" class="form-control"></textarea>
                </div>
                <button type="button" class="btn btn-danger btn-sm btn-remove-resolution">Usuń tę uchwałę</button>
            `;
            resolutionsList.appendChild(newResolutionDiv);
            updateRemoveButtons();
        });

        function updateRemoveButtons() {
            const removeButtons = resolutionsList.querySelectorAll('.btn-remove-resolution');
            removeButtons.forEach(button => {
                button.removeEventListener('click', handleRemoveResolution); // Remove old listener
                button.addEventListener('click', handleRemoveResolution); // Add new one
            });
        }

        function handleRemoveResolution(event) {
            // Don't remove if it's the last one, as one resolution is required.
            if (resolutionsList.children.length > 1) {
                event.target.closest('.resolution-input-group').remove();
                // Re-number resolutions if needed (optional, depends on backend handling)
                // For now, just decrement counter if we were to re-use it simply
                // resolutionCounter--; // This needs more robust handling if we re-number visually
            } else {
                alert('Musi pozostać przynajmniej jedna uchwała.');
            }
        }
        updateRemoveButtons(); // Initial call for existing buttons
    }


    // --- Vote Page: Simulate Voting Interaction ---
    const voteButtons = document.querySelectorAll('.vote-options .btn');
    const voteStatusMessage = document.getElementById('vote-status-message');
    const currentResolutionDisplay = document.getElementById('current-resolution-area');
    const waitingMessage = document.getElementById('waiting-for-resolution');
    const resolutionResultsSummary = document.getElementById('resolution-results-summary');

    if (voteButtons.length > 0 && voteStatusMessage) {
        voteButtons.forEach(button => {
            button.addEventListener('click', function() {
                // In a real app, this would send an AJAX request
                const voteType = this.dataset.vote;
                
                // Disable all vote buttons
                voteButtons.forEach(btn => btn.disabled = true);
                
                voteStatusMessage.textContent = `Twój głos "${this.textContent}" został zarejestrowany.`;
                voteStatusMessage.className = 'alert alert-success';
                voteStatusMessage.classList.remove('d-none');

                // Simulate director ending vote and showing results after a delay
                setTimeout(() => {
                    if (resolutionResultsSummary) {
                        resolutionResultsSummary.innerHTML = `
                            <h4>Wyniki głosowania nad uchwałą:</h4>
                            <p>Za: 12</p>
                            <p>Przeciw: 3</p>
                            <p>Wstrzymano się: 1</p>
                            <p class="mt-2">Oczekiwanie na kolejną uchwałę lub zakończenie sesji przez dyrektora...</p>
                        `;
                        resolutionResultsSummary.classList.remove('d-none');
                        if (currentResolutionDisplay) {
                             // currentResolutionDisplay.classList.add('d-none'); // Hide current voting area
                        }
                    }
                }, 2000);
            });
        });
    }

    // Simulate director starting a vote on vote.php
    if (waitingMessage && currentResolutionDisplay) {
        setTimeout(() => {
            waitingMessage.classList.add('d-none');
            currentResolutionDisplay.classList.remove('d-none');
            // Potentially populate resolution details here via JS if not hardcoded
        }, 3000); // Show voting area after 3 seconds
    }


    // --- Admin Dashboard: Simulate starting a session and getting a code ---
    const startSessionButton = document.getElementById('start-selected-session-btn'); // Assume we select a session first
    const sessionCodeDisplay = document.getElementById('generated-session-code-display');

    if (startSessionButton && sessionCodeDisplay) {
        startSessionButton.addEventListener('click', function() {
            // In real app, AJAX call to backend
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
        });
    }


    // --- Basic Form Validation Example (can be expanded) ---
    const formsToValidate = document.querySelectorAll('form.needs-validation');
    formsToValidate.forEach(form => {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            const requiredInputs = form.querySelectorAll('[required]');
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid'); // Needs CSS for .is-invalid
                    // You might want to add error messages next to fields
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                event.preventDefault(); // Stop submission
                alert('Proszę wypełnić wszystkie wymagane pola.'); // Simple alert
            }
        });
    });

});