document.addEventListener('DOMContentLoaded', function() {
    console.log('scripts.js loaded and DOMContentLoaded event fired'); 

    const API_BASE_URL = 'api/'; 
    const LS_PREFIX = 'votumNauczycieli_'; 


    // --- Helper Functions ---
    function displayMessage(elementId, message, isSuccess = true, clearAfter = 0) {
        const messageEl = document.getElementById(elementId);
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = 'alert'; 
            if (message) {
                messageEl.classList.add(isSuccess ? 'alert-success' : 'alert-danger');
                messageEl.classList.remove('d-none');

                if (clearAfter > 0) {
                    setTimeout(() => {
                        messageEl.classList.add('d-none');
                        messageEl.textContent = '';
                    }, clearAfter);
                }
            } else {
                messageEl.classList.add('d-none');
            }
        } else {
            console.warn(`displayMessage: Element with ID '${elementId}' not found.`);
        }
    }

    async function apiFetch(endpoint, options = {}) {
        console.log(`apiFetch: Calling endpoint '${API_BASE_URL}${endpoint}' with options:`, options); 
        try {
            const response = await fetch(API_BASE_URL + endpoint, options);
            console.log(`apiFetch: Response status for '${endpoint}': ${response.status}`); 
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error(`Nie znaleziono zasobu (404): ${API_BASE_URL}${endpoint}`);
                }
                if (response.headers.get("content-type")?.includes("application/json")) {
                    const errorData = await response.json();
                    console.error(`apiFetch: Error data for '${endpoint}':`, errorData); 
                    throw new Error(errorData.message || `HTTP error ${response.status}`);
                }
                throw new Error(`HTTP error ${response.status} for ${API_BASE_URL}${endpoint}`);
            }
            if (response.status === 204) {
                console.log(`apiFetch: Empty response (204 No Content) for '${endpoint}'`);
                return null; 
            }
            if (response.headers.get("content-type")?.includes("application/json")) {
                const jsonData = await response.json();
                console.log(`apiFetch: JSON response for '${endpoint}':`, jsonData); 
                return jsonData;
            }
            const textData = await response.text();
            console.log(`apiFetch: Text response for '${endpoint}':`, textData); 
            return textData; 
        } catch (error) {
            console.error(`apiFetch: Catch block error for '${API_BASE_URL}${endpoint}':`, error.message); 
            throw error; 
        }
    }

    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function (match) {
            return {
                '&': '&',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": ''
            }[match];
        });
    }
    
    const Vote = { 
        CHOICE_YES: 'tak',
        CHOICE_NO: 'nie',
        CHOICE_ABSTAIN: 'wstrzymanie'
    };

    // --- Authentication Logic (Admin Login) ---
    async function handleLogin(event) { 
        console.log('handleLogin (Admin): Function triggered.'); 
        event.preventDefault(); 
        
        const form = event.target;
        const emailInput = form.querySelector('input[name="email"]'); 
        const passwordInput = form.querySelector('input[name="password"]');
        const messageElId = 'login-message';

        if (!emailInput || !passwordInput) {
            console.error('handleLogin (Admin): Email or password input not found.');
            displayMessage(messageElId, 'Błąd formularza: brak pól email lub hasła.', false);
            return;
        }

        const email = emailInput.value;
        const password = passwordInput.value;
        displayMessage(messageElId, ''); 

        try {
            const data = await apiFetch('login_endpoint.php', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            if (data.status === 'success' && data.user && data.user.role === 'admin') {
                displayMessage(messageElId, data.message, true);
                saveToLocalStorage('currentUser', data.user); 
                if (data.redirectUrl) {
                    window.location.href = data.redirectUrl; 
                } else {
                    updateNavigation(data.user);
                }
            } else {
                const errorMsg = data.message || (data.user && data.user.role !== 'admin' ? 'Logowanie dostępne tylko dla administratorów.' : 'Logowanie nie powiodło się.');
                displayMessage(messageElId, errorMsg, false);
            }
        } catch (error) {
            displayMessage(messageElId, error.message || 'Wystąpił błąd podczas logowania administratora.', false);
        }
    }

    async function handleLogout(redirect = true) { 
        console.log('handleLogout: Function triggered.');
        const logoutPageMessageEl = document.getElementById('logout-page-message');
        
        if (logoutPageMessageEl) logoutPageMessageEl.textContent = 'Wylogowywanie, proszę czekać...';

        try {
            const data = await apiFetch('logout_endpoint.php', { method: 'POST' });
            console.log('handleLogout: Data received from logout_endpoint.php:', data);
            if (data.status === 'success') {
                if (logoutPageMessageEl) logoutPageMessageEl.textContent = data.message || 'Wylogowano pomyślnie.';
            } else {
                if (logoutPageMessageEl) logoutPageMessageEl.textContent = data.message || 'Nie udało się wylogować po stronie serwera, ale sesja frontendu została wyczyszczona.';
            }
        } catch (error) {
            console.error('handleLogout: Error during logout process:', error);
            if (logoutPageMessageEl) logoutPageMessageEl.textContent = error.message || 'Wystąpił błąd podczas wylogowywania.';
        } finally {
            removeFromLocalStorage('currentUser'); 
            removeFromLocalStorage('currentVotingSession'); 
            removeFromLocalStorage('last_joined_session_code'); 
            updateNavigation(null); 

            if (redirect) {
                if (logoutPageMessageEl && !logoutPageMessageEl.textContent.includes('Za chwilę')) {
                     logoutPageMessageEl.textContent += ' Za chwilę zostaniesz przekierowany.';
                }
                setTimeout(() => {
                    console.log('handleLogout: Redirecting to join_session.php (default after logout/clear).');
                    window.location.href = 'join_session.php'; 
                }, 1500); 
            }
        }
    }


    async function checkLoginStatusAndUpdateNav() {
        console.log('checkLoginStatusAndUpdateNav: Function triggered.');
        let adminUser = loadFromLocalStorage('currentUser'); 
        
        if (adminUser && adminUser.role !== 'admin') { 
            adminUser = null;
            removeFromLocalStorage('currentUser');
        }
        console.log('checkLoginStatusAndUpdateNav: Admin from LocalStorage:', adminUser);

        if (!adminUser) { 
            try {
                const data = await apiFetch('check_session.php'); 
                if (data.loggedIn && data.user && data.user.role === 'admin') {
                    adminUser = data.user;
                    saveToLocalStorage('currentUser', adminUser);
                } else {
                    removeFromLocalStorage('currentUser'); 
                }
            } catch (error) {
                console.warn('checkLoginStatusAndUpdateNav: Failed to check admin session status with server:', error);
                removeFromLocalStorage('currentUser');
                adminUser = null; 
            }
        }
        
        updateNavigation(adminUser); 

        const currentPage = window.location.pathname.split('/').pop() || 'index.php'; 
        const adminOnlyPages = ['admin_dashboard.php', 'create_session.php', 'history.php', 'results.php'];
        
        if (adminUser) { 
            const adminDashboardUrl = 'admin_dashboard.php';
            if ((currentPage === 'index.php' || currentPage === 'join_session.php') && !window.location.pathname.endsWith(adminDashboardUrl)) {
                 window.location.href = adminDashboardUrl;
            }
        } else { 
            if (adminOnlyPages.includes(currentPage)) {
                window.location.href = 'index.php?redirect_message=admin_login_required_for_' + currentPage; 
            }
        }
    }

    function updateNavigation(adminUser) { 
        console.log('updateNavigation: Updating navigation for adminUser:', adminUser);
        const nav = document.getElementById('main-navigation');
        if (!nav) return;

        let navLinks = '';
        if (adminUser && adminUser.role === 'admin') { 
            navLinks += '<a href="admin_dashboard.php">Panel Dyrektora</a>';
            navLinks += '<a href="create_session.php">Utwórz Sesję</a>';
            navLinks += '<a href="history.php">Historia Głosowań</a>';
            navLinks += `<span style="color: var(--color-text-dark); margin-left: 15px; margin-right:15px; font-size: 0.9em;">Admin: ${htmlspecialchars(adminUser.name)}</span>`;
            navLinks += '<a href="logout.php" class="nav-link-logout" id="logout-link">Wyloguj</a>';
        } else { 
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            navLinks += '<a href="join_session.php">Dołącz do Sesji</a>';
            if (currentPage !== 'index.php') { 
                navLinks += '<a href="index.php">Logowanie Admina</a>';
            }
        }
        nav.innerHTML = navLinks;

        const logoutLink = document.getElementById('logout-link');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(event) {
                event.preventDefault(); 
                handleLogout(true);     
            });
        }
    }

    // --- Create Session Logic ---
    async function handleCreateSession(event) {
        console.log('handleCreateSession: Function triggered.');
        event.preventDefault();
        const form = event.target; 
        const messageElId = 'create-session-message';
        displayMessage(messageElId, ''); 

        const titleInput = form.querySelector('input[name="session_title"]');
        if (!titleInput) {
            console.error('handleCreateSession: Title input (name="session_title") not found.');
            displayMessage(messageElId, 'Błąd formularza: brak pola tytułu.', false);
            return;
        }
        const title = titleInput.value.trim();

        const resolutionTextareas = form.querySelectorAll('textarea[name="resolutions_texts[]"]');
        const resolutions = [];
        resolutionTextareas.forEach(textarea => {
            const text = textarea.value.trim();
            if (text) { 
                resolutions.push(text);
            }
        });
        console.log('handleCreateSession: Title:', title, 'Resolutions:', resolutions);

        if (!title) {
            displayMessage(messageElId, 'Tytuł sesji jest wymagany.', false);
            return;
        }
        if (resolutions.length === 0) {
            displayMessage(messageElId, 'Musi być przynajmniej jedna uchwała z treścią.', false);
            return;
        }

        try {
            const payload = { title: title, resolutions: resolutions }; 
            const data = await apiFetch('create_session_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            console.log('handleCreateSession: Data received from create_session_endpoint.php:', data);

            if (data.status === 'success') {
                displayMessage(messageElId, data.message, true);
                removeFromLocalStorage('create_session_form_data'); 
                if (data.redirectUrl) {
                    setTimeout(() => { 
                        console.log(`handleCreateSession: Redirecting to ${data.redirectUrl}`);
                        window.location.href = data.redirectUrl;
                    }, 1500);
                }
            } else {
                displayMessage(messageElId, data.message || 'Nie udało się utworzyć sesji.', false);
            }
        } catch (error) {
            console.error('handleCreateSession: Error during session creation:', error.message);
            displayMessage(messageElId, error.message || 'Wystąpił błąd podczas tworzenia sesji.', false);
        }
    }

    // --- Admin Dashboard Logic ---
    async function loadAdminDashboardData() {
        console.log('loadAdminDashboardData: Function triggered.');
        const activeSessionsListEl = document.getElementById('active-sessions-list');
        const closedSessionsListEl = document.getElementById('closed-sessions-list');
        const dashboardMessageElId = 'dashboard-message';

        if (!activeSessionsListEl && !closedSessionsListEl) {
            console.log('loadAdminDashboardData: Not on admin dashboard or missing list elements.');
            return;
        }
        
        displayMessage(dashboardMessageElId, '');

        if (activeSessionsListEl) {
            activeSessionsListEl.innerHTML = '<p class="text-center">Ładowanie aktywnych sesji...</p>';
            try {
                const activeSessions = await apiFetch('admin/list_sessions.php?status=open');
                if (activeSessions.status === 'success') {
                    renderSessionsList(activeSessionsListEl, activeSessions.data, 'aktywne');
                } else {
                    activeSessionsListEl.innerHTML = `<p class="alert alert-warning">${htmlspecialchars(activeSessions.message) || 'Nie udało się załadować aktywnych sesji.'}</p>`;
                }
            } catch (error) {
                activeSessionsListEl.innerHTML = `<p class="alert alert-danger">Błąd podczas ładowania aktywnych sesji: ${htmlspecialchars(error.message)}</p>`;
            }
        }

        if (closedSessionsListEl) {
            closedSessionsListEl.innerHTML = '<p class="text-center">Ładowanie zamkniętych sesji...</p>';
            try {
                const closedSessions = await apiFetch('admin/list_sessions.php?status=closed');
                if (closedSessions.status === 'success') {
                    renderSessionsList(closedSessionsListEl, closedSessions.data, 'zamknięte');
                } else {
                    closedSessionsListEl.innerHTML = `<p class="alert alert-warning">${htmlspecialchars(closedSessions.message) || 'Nie udało się załadować zamkniętych sesji.'}</p>`;
                }
            } catch (error) {
                closedSessionsListEl.innerHTML = `<p class="alert alert-danger">Błąd podczas ładowania zamkniętych sesji: ${htmlspecialchars(error.message)}</p>`;
            }
        }
    }

    function renderSessionsList(containerElement, sessions, type) {
        console.log(`renderSessionsList: Rendering ${type} sessions:`, sessions);
        if (!sessions || sessions.length === 0) {
            containerElement.innerHTML = `<p class="alert alert-info mt-2">Brak ${type} sesji do wyświetlenia.</p>`;
            return;
        }

        let html = '<ul class="list-group mt-2">';
        sessions.forEach(session => {
            const createdAtDate = session.created_at ? new Date(session.created_at).toLocaleDateString('pl-PL', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Brak danych';
            const statusBadgeClass = session.status === 'open' ? 'badge-success' : (session.status === 'closed' ? 'badge-secondary' : 'badge-light');
            const statusText = session.status === 'open' ? 'Otwarta' : (session.status === 'closed' ? 'Zamknięta' : htmlspecialchars(session.status));
            
            html += `
                <li class="list-group-item session-item-admin content-card mb-3 p-3">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1 card-title">${htmlspecialchars(session.title)} (ID: ${session.session_id})</h5>
                        <small class="text-muted">Utworzono: ${createdAtDate}</small>
                    </div>
                    <p class="mb-1">Kod dostępu: <strong>${htmlspecialchars(session.code)}</strong> | Status: <span class="badge ${statusBadgeClass}">${statusText}</span></p>
                    <p class="mb-1">Liczba uchwał: ${session.resolutions ? session.resolutions.length : 'B/D'}</p>
                    <div class="mt-2 session-actions">
                        ${session.status === 'open' ? 
                            `<button class="btn btn-sm btn-danger btn-close-session mr-2" data-session-id="${session.session_id}"><i class="fas fa-lock"></i> Zamknij Sesję</button>
                             <a href="results.php?session_id=${session.session_id}" class="btn btn-sm btn-primary"><i class="fas fa-tasks"></i> Zarządzaj / Postęp</a>` :
                            `<a href="results.php?session_id=${session.session_id}" class="btn btn-sm btn-info"><i class="fas fa-poll-h"></i> Zobacz Wyniki</a>`
                        }
                    </div>
                </li>`;
        });
        html += '</ul>';
        containerElement.innerHTML = html;

        containerElement.querySelectorAll('.btn-close-session').forEach(button => {
            button.addEventListener('click', handleCloseSessionClick);
        });
    }

    async function handleCloseSessionClick(event) {
        const button = event.currentTarget;
        const sessionId = button.dataset.sessionId;
        if (!sessionId) return;

        if (!confirm(`Czy na pewno chcesz zamknąć sesję o ID: ${sessionId}? Tej operacji nie można cofnąć.`)) {
            return;
        }
        
        const dashboardMessageElId = 'dashboard-message'; 
        displayMessage(dashboardMessageElId, `Zamykanie sesji ID: ${sessionId}...`, true);
        button.disabled = true;

        try {
            const result = await apiFetch('admin/close_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: parseInt(sessionId) })
            });

            if (result.status === 'success') {
                displayMessage(dashboardMessageElId, result.message || `Sesja ID: ${sessionId} została zamknięta.`, true, 5000);
                loadAdminDashboardData(); 
            } else {
                displayMessage(dashboardMessageElId, result.message || 'Nie udało się zamknąć sesji.', false, 7000);
                button.disabled = false;
            }
        } catch (error) {
            displayMessage(dashboardMessageElId, `Błąd podczas zamykania sesji: ${htmlspecialchars(error.message)}`, false, 7000);
            button.disabled = false;
        }
    }

    // --- Join Session Logic --- (ANONIMOWE)
    async function handleJoinSession(event) {
        event.preventDefault();
        console.log('handleJoinSession: Function triggered (Anonymous).');
        const form = event.target;
        const messageElId = 'join-session-message';
        displayMessage(messageElId, ''); 

        const sessionCodeInput = form.querySelector('input[name="session_code"]');
        
        if (!sessionCodeInput) {
            console.error('handleJoinSession: Session code input not found.');
            displayMessage(messageElId, 'Błąd formularza: brak pola kodu sesji.', false);
            return;
        }
        const sessionCode = sessionCodeInput.value.trim().toUpperCase();

        if (!sessionCode) {
            displayMessage(messageElId, 'Kod sesji jest wymagany.', false);
            sessionCodeInput.focus();
            return;
        }
        
        console.log(`handleJoinSession: Attempting to join session with code: ${sessionCode}`);

        try {
            const data = await apiFetch('join_session_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_code: sessionCode }) // Tylko kod sesji
            });
            console.log('handleJoinSession: Data received from join_session_endpoint.php:', data);

            if (data.status === 'success') {
                displayMessage(messageElId, data.message, true);
                if (data.data && data.data.session_id) { 
                    saveToLocalStorage('currentVotingSession', { 
                        sessionId: data.data.session_id,
                        sessionCode: data.data.code,
                        sessionTitle: data.data.title,
                    });
                    saveToLocalStorage('last_joined_session_code', data.data.code); 
                    console.log('handleJoinSession: Voting session info saved to LocalStorage.');
                }

                if (data.redirectUrl) {
                    console.log(`handleJoinSession: Redirecting to ${data.redirectUrl}`);
                    setTimeout(() => { 
                        window.location.href = data.redirectUrl;
                    }, 1500);
                }
            } else {
                displayMessage(messageElId, data.message || 'Nie udało się dołączyć do sesji.', false);
            }
        } catch (error) {
            console.error('handleJoinSession: Error during join session process:', error);
            displayMessage(messageElId, error.message || 'Wystąpił błąd podczas próby dołączenia do sesji.', false);
        }
    }

    // --- Vote Page Logic ---
    let currentVotingSessionDataFromAPI = null; 

    async function loadVotingPageData() {
        console.log('loadVotingPageData: Function triggered.');
        const resolutionsArea = document.getElementById('resolutions-voting-area');
        const sessionTitleEl = document.getElementById('vote-session-title');
        const votePageMessageElId = 'vote-page-message';
        const finishVotingBtn = document.getElementById('finish-voting-btn');
        const participantGreetingEl = document.getElementById('vote-participant-greeting');

        if (!resolutionsArea || !sessionTitleEl) {
            console.log('loadVotingPageData: Not on vote page or missing key elements.');
            return;
        }
        displayMessage(votePageMessageElId, '');
        resolutionsArea.innerHTML = '<p class="text-center">Ładowanie danych sesji głosowania...</p>';
        if (participantGreetingEl) participantGreetingEl.style.display = 'none'; // Ukryj powitanie, bo jest anonimowe

        try {
            const response = await apiFetch('get_session_for_voting.php'); 
            console.log('loadVotingPageData: Response from get_session_for_voting.php:', response);

            if (response.status === 'success' && response.data && response.data.session) {
                currentVotingSessionDataFromAPI = response.data; 
                const session = response.data.session;
                // Usunięto wyświetlanie display_identifier

                if (sessionTitleEl) {
                    sessionTitleEl.textContent = `Panel Głosowania: ${htmlspecialchars(session.title)}`;
                }
                
                if (session.resolutions && session.resolutions.length > 0) {
                    renderResolutionsForVoting(resolutionsArea, session.resolutions);
                } else {
                    resolutionsArea.innerHTML = '<p class="alert alert-info text-center">Obecnie żadna uchwała nie jest aktywna do głosowania lub wszystkie zostały już przegłosowane. <br>Odśwież stronę, aby sprawdzić dostępność nowych uchwał.</p>';
                }
                
                if (finishVotingBtn) {
                    finishVotingBtn.classList.remove('d-none');
                    finishVotingBtn.onclick = () => { 
                        removeFromLocalStorage('currentVotingSession');
                        console.log('finish-voting-btn: Clicked, redirecting to join_session.php');
                        window.location.href = 'join_session.php?info=voting_panel_left_anon';
                    };
                }

            } else {
                const message = response.message || 'Nie udało się załadować danych sesji głosowania.';
                displayMessage(votePageMessageElId, message, false);
                resolutionsArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(message)}</p>`;
                if (response.action === 'redirect_to_join') {
                    setTimeout(() => { window.location.href = 'join_session.php?reason=session_data_load_failed_or_ended_anon'; }, 3000);
                } else if (response.action === 'show_results_or_redirect' && response.session_status === 'closed') {
                    resolutionsArea.innerHTML += `<p class="alert alert-info mt-2">Ta sesja jest zamknięta. Nie można już głosować.</p>`;
                    if (finishVotingBtn) finishVotingBtn.classList.remove('d-none'); 
                } else {
                     if (finishVotingBtn) finishVotingBtn.classList.remove('d-none'); 
                }
            }
        } catch (error) {
            console.error('loadVotingPageData: Error loading voting session data:', error);
            const errorMessage = error.message || 'Wystąpił krytyczny błąd podczas ładowania danych sesji.';
            displayMessage(votePageMessageElId, errorMessage, false);
            resolutionsArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(errorMessage)}</p>`;
            if (finishVotingBtn) finishVotingBtn.classList.remove('d-none');
            if (error.message.toLowerCase().includes('nie dołączyłeś do żadnej sesji') || 
                error.message.toLowerCase().includes('brak danych o sesji')) {
                setTimeout(() => { window.location.href = 'join_session.php?reason=session_ended_or_error_strict_anon'; }, 3000);
            }
        }
    }

    function renderResolutionsForVoting(container, resolutions) {
        console.log('renderResolutionsForVoting: Rendering resolutions:', resolutions);
        if (!resolutions || resolutions.length === 0) {
            // Komunikat jest już obsługiwany w loadVotingPageData, jeśli lista jest pusta PO przefiltrowaniu przez API
            // Tutaj można dać fallback, jeśli API zwróciło pustą listę, a powinno coś być
            container.innerHTML = '<p class="alert alert-info text-center">Brak uchwał do wyświetlenia lub oczekiwanie na aktywację przez dyrektora.</p>';
            return;
        }

        let html = '';
        let activeResolutionFound = false;
        resolutions.forEach((resolution, index) => {
            const resolutionNumber = resolution.number || (index + 1);
            const hasVoted = resolution.voted_choice !== null;
            const votedChoice = resolution.voted_choice;
            const isVotingActive = resolution.voting_status === 'active'; 
            
            if (isVotingActive) activeResolutionFound = true;

            html += `
                <div class="resolution-item content-card mb-3" id="resolution-item-${resolution.resolution_id}" data-voting-status="${resolution.voting_status}">
                    <h4 class="card-title">Uchwała #${resolutionNumber}: ${htmlspecialchars(resolution.text)}</h4>
            `;

            if (isVotingActive) { 
                html += `
                    <div id="vote-options-${resolution.resolution_id}" class="vote-options mt-3">
                        <button class="btn btn-success vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="tak" ${hasVoted ? 'disabled' : ''}>
                            <i class="fas fa-check"></i> Tak
                        </button>
                        <button class="btn btn-danger vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="nie" ${hasVoted ? 'disabled' : ''}>
                            <i class="fas fa-times"></i> Nie
                        </button>
                        <button class="btn btn-warning vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="wstrzymanie" ${hasVoted ? 'disabled' : ''}>
                            <i class="fas fa-pause-circle"></i> Wstrzymuję się
                        </button>
                    </div>
                `;
            } else if (resolution.voting_status === 'closed') {
                html += `<p class="text-info mt-2 font-italic">Głosowanie nad tą uchwałą zostało zakończone.</p>`;
            }
            
            html += `
                    <div id="vote-status-${resolution.resolution_id}" class="mt-2" style="font-size: 0.9em; color: var(--color-success);">
                        ${hasVoted ? `Twój głos: <strong>${votedChoice === 'tak' ? 'Na Tak' : (votedChoice === 'nie' ? 'Na Nie' : 'Wstrzymano się')}</strong>` : (resolution.voting_status === 'closed' ? '<span class="text-muted font-italic">Nie oddano głosu w trakcie trwania głosowania.</span>' : '')}
                    </div>
                </div>
            `; 
        });

        if (!activeResolutionFound && resolutions.some(r => r.voting_status === 'closed')) {
             html += '<p class="alert alert-info text-center mt-3">Wszystkie dostępne uchwały zostały już przegłosowane. Możesz opuścić panel.</p>';
        } else if (!activeResolutionFound && resolutions.every(r => r.voting_status === 'pending')) {
            // To nie powinno się zdarzyć, bo API filtruje, ale na wszelki wypadek
            html = '<p class="alert alert-info text-center">Oczekiwanie na rozpoczęcie głosowania nad uchwałą przez dyrektora...</p>';
        }


        container.innerHTML = html;

        container.querySelectorAll('.vote-btn').forEach(button => {
            button.addEventListener('click', handleVoteButtonClick);
        });
    }

    async function handleVoteButtonClick(event) {
        const button = event.target.closest('.vote-btn'); 
        if (!button) return;

        const resolutionId = button.dataset.resolutionId;
        const choice = button.dataset.choice;
        const resolutionItemStatusEl = document.getElementById(`vote-status-${resolutionId}`);

        console.log(`handleVoteButtonClick: resolutionId=${resolutionId}, choice=${choice}`);

        const optionsDiv = document.getElementById(`vote-options-${resolutionId}`);
        if (optionsDiv) {
            optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                btn.disabled = true; 
                btn.style.opacity = '0.5'; 
            });
            button.style.opacity = '1'; 
        }
        if (resolutionItemStatusEl) {
            resolutionItemStatusEl.textContent = 'Przetwarzanie głosu...';
            resolutionItemStatusEl.style.color = 'var(--color-text-muted)'; 
        }

        try {
            const response = await apiFetch('vote_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ resolution_id: parseInt(resolutionId), choice: choice })
            });
            console.log('handleVoteButtonClick: Response from vote_endpoint.php:', response);

            if (response.status === 'success' && response.data) {
                if (resolutionItemStatusEl) {
                    resolutionItemStatusEl.textContent = `Twój głos (${htmlspecialchars(response.data.choice)}) został pomyślnie zapisany.`;
                    resolutionItemStatusEl.style.color = 'var(--color-success)';
                }

                if (optionsDiv) {
                    optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                        btn.disabled = true; // Po zagłosowaniu wszystkie przyciski dla tej uchwały są zablokowane
                        if (btn.dataset.choice === response.data.choice) {
                            btn.classList.add('active'); 
                            btn.innerHTML = `<i class="fas ${response.data.choice === 'tak' ? 'fa-check' : (response.data.choice === 'nie' ? 'fa-times' : 'fa-pause-circle')}"></i> ${btn.textContent.split('(')[0].trim()} (Twój głos)`;
                            btn.style.opacity = '1'; // Aktywny głos pełna opacity
                        } else {
                            btn.style.opacity = '0.65'; 
                        }
                    });
                }
                
                if (currentVotingSessionDataFromAPI && currentVotingSessionDataFromAPI.session && currentVotingSessionDataFromAPI.session.resolutions) {
                    const resIndex = currentVotingSessionDataFromAPI.session.resolutions.findIndex(r => r.resolution_id == resolutionId);
                    if (resIndex > -1) {
                        currentVotingSessionDataFromAPI.session.resolutions[resIndex].voted_choice = response.data.choice;
                    }
                }

            } else {
                const message = response.message || 'Nie udało się zapisać głosu.';
                if (resolutionItemStatusEl) {
                    resolutionItemStatusEl.textContent = message;
                    resolutionItemStatusEl.style.color = 'var(--color-accent)';
                }
                if (optionsDiv) { // Przywróć przyciski do stanu sprzed próby
                    optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                        const resolutionData = currentVotingSessionDataFromAPI?.session?.resolutions.find(r => r.resolution_id == resolutionId);
                        const previousChoice = resolutionData?.voted_choice; // Zakładamy, że to jest null jeśli nie głosowano
                        
                        btn.disabled = (previousChoice !== null); // Jeśli już głosowano (nawet jeśli to był inny wybór), przyciski pozostają zablokowane po błędzie
                                                                // Chyba że chcemy pozwolić na ponowną próbę.
                                                                // Na razie: jeśli był głos, blokuj. Jeśli nie było, odblokuj.
                        btn.style.opacity = (previousChoice !== null) ? '0.5' : '1';
                        if (btn.dataset.choice === previousChoice) {
                            btn.style.opacity = '1';
                            btn.classList.add('active');
                            btn.innerHTML = `<i class="fas ${previousChoice === 'tak' ? 'fa-check' : (previousChoice === 'nie' ? 'fa-times' : 'fa-pause-circle')}"></i> ${btn.textContent.split('(')[0].trim()} (Twój głos)`;
                        } else {
                             let baseText = '';
                             if (btn.dataset.choice === 'tak') baseText = 'Tak';
                             else if (btn.dataset.choice === 'nie') baseText = 'Nie';
                             else if (btn.dataset.choice === 'wstrzymanie') baseText = 'Wstrzymuję się';
                             
                             let iconClass = '';
                             if (btn.dataset.choice === 'tak') iconClass = 'fa-check';
                             else if (btn.dataset.choice === 'nie') iconClass = 'fa-times';
                             else if (btn.dataset.choice === 'wstrzymanie') iconClass = 'fa-pause-circle';
                             btn.innerHTML = `<i class="fas ${iconClass}"></i> ${baseText}`;
                        }
                    });
                }
            }

        } catch (error) {
            console.error('handleVoteButtonClick: Error during voting:', error);
            const errorMessage = error.message || 'Wystąpił błąd podczas zapisywania głosu.';
            if (resolutionItemStatusEl) {
                resolutionItemStatusEl.textContent = errorMessage;
                resolutionItemStatusEl.style.color = 'var(--color-accent)';
            }
            if (optionsDiv) { // Przywróć przyciski
                 optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                    const resolutionData = currentVotingSessionDataFromAPI?.session?.resolutions.find(r => r.resolution_id == resolutionId);
                    const previousChoice = resolutionData?.voted_choice;
                    btn.disabled = (previousChoice !== null);
                    btn.style.opacity = (previousChoice !== null) ? '0.5' : '1';
                    // ... (reszta logiki przywracania tekstu i ikon jak wyżej)
                    if (btn.dataset.choice === previousChoice) {
                        btn.style.opacity = '1';
                        btn.classList.add('active');
                        btn.innerHTML = `<i class="fas ${previousChoice === 'tak' ? 'fa-check' : (previousChoice === 'nie' ? 'fa-times' : 'fa-pause-circle')}"></i> ${btn.textContent.split('(')[0].trim()} (Twój głos)`;
                    } else {
                         let baseText = '';
                         if (btn.dataset.choice === 'tak') baseText = 'Tak';
                         else if (btn.dataset.choice === 'nie') baseText = 'Nie';
                         else if (btn.dataset.choice === 'wstrzymanie') baseText = 'Wstrzymuję się';
                         
                         let iconClass = '';
                         if (btn.dataset.choice === 'tak') iconClass = 'fa-check';
                         else if (btn.dataset.choice === 'nie') iconClass = 'fa-times';
                         else if (btn.dataset.choice === 'wstrzymanie') iconClass = 'fa-pause-circle';
                         btn.innerHTML = `<i class="fas ${iconClass}"></i> ${baseText}`;
                    }
                });
            }
        }
    }

    // --- Results Page Logic (Zarządzanie Uchwałami i Wynikami) ---
    let currentResultsSessionId = null; 

async function loadResultsPageData() {
    console.log('loadResultsPageData: Function triggered.');
    const resultsDataDisplayArea = document.getElementById('session-data-area'); // Zmieniono nazwę zmiennej dla jasności
    const resolutionsManagementArea = document.getElementById('resolutions-list-admin'); 
    const sessionTitleEl = document.getElementById('results-session-title');
    const resultsPageMessageElId = 'results-page-message';
    const sessionStatusInfoEl = document.getElementById('session-status-info');
    const closeThisSessionBtn = document.getElementById('close-this-session-btn');
    const progressResultsHeaderEl = document.getElementById('progress-results-header');

    const urlParams = new URLSearchParams(window.location.search);
    // currentResultsSessionId jest już zdefiniowane globalnie (module scope) lub powinno być przekazane/ustawione
    // Dla pewności, pobierzmy je tutaj ponownie, jeśli nie jest to zmienna globalna w module.
    // Zakładając, że currentResultsSessionId jest już dostępne:
    if (!currentResultsSessionId) { // Jeśli nie, to spróbuj pobrać z URL
        currentResultsSessionId = urlParams.get('session_id');
    }


    if (!currentResultsSessionId) {
        displayMessage(resultsPageMessageElId, 'Brak ID sesji w URL.', false);
        if (resultsDataDisplayArea) resultsDataDisplayArea.innerHTML = '<p class="alert alert-danger">Nie określono ID sesji.</p>';
        if (resolutionsManagementArea) resolutionsManagementArea.innerHTML = '';
        return;
    }
    
    if (!resultsDataDisplayArea || !sessionTitleEl || !sessionStatusInfoEl || !resolutionsManagementArea || !progressResultsHeaderEl) {
        console.warn('loadResultsPageData: Not on results page or missing one or more key elements (resultsDataDisplayArea, sessionTitleEl, sessionStatusInfoEl, resolutionsManagementArea, progressResultsHeaderEl).');
        return;
    }

    displayMessage(resultsPageMessageElId, '');
    resultsDataDisplayArea.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Ładowanie danych ogólnych...</p>'; // Dodano spinner
    resolutionsManagementArea.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Ładowanie listy uchwał...</p>'; // Dodano spinner
    sessionStatusInfoEl.innerHTML = '';
    if (closeThisSessionBtn) closeThisSessionBtn.classList.add('d-none');

    try {
        const response = await apiFetch(`admin/get_session_results.php?session_id=${currentResultsSessionId}`);
        console.log('loadResultsPageData: Response from get_session_results.php:', response);

        if (response.status === 'success') {
            let sessionDetailsForDisplay;
            let resolutionsForAdminList = [];
            let mainSessionStatus = 'unknown';
            let progressSpecificData = null; 
            let resultsSpecificData = null;  

            if (response.type === 'progress' && response.session_info) {
                sessionDetailsForDisplay = response.session_info;
                resolutionsForAdminList = sessionDetailsForDisplay.resolutions || [];
                mainSessionStatus = sessionDetailsForDisplay.status;
                progressSpecificData = response.progress_details; 
            } else if (response.type === 'results' && response.data) {
                sessionDetailsForDisplay = response.data; 
                resolutionsForAdminList = sessionDetailsForDisplay.resolutions || 
                                          (sessionDetailsForDisplay.resolutions_results ? 
                                            sessionDetailsForDisplay.resolutions_results.map(r => ({
                                                resolution_id: r.resolution_id, text: r.text, number: r.number, voting_status: 'closed'
                                            })) : []);
                mainSessionStatus = sessionDetailsForDisplay.status;
                resultsSpecificData = response.data; 
            } else {
                displayMessage(resultsPageMessageElId, 'Otrzymano niekompletną lub nieoczekiwaną strukturę odpowiedzi z serwera.', false);
                resultsDataDisplayArea.innerHTML = '<p class="alert alert-danger">Niekompletna odpowiedź serwera (brak typu lub danych).</p>';
                resolutionsManagementArea.innerHTML = '';
                return;
            }
            
            if (!sessionDetailsForDisplay || !sessionDetailsForDisplay.session_id) {
                displayMessage(resultsPageMessageElId, 'Brak kluczowych danych sesji w odpowiedzi.', false);
                resultsDataDisplayArea.innerHTML = '<p class="alert alert-danger">Brak kluczowych danych sesji.</p>';
                resolutionsManagementArea.innerHTML = '';
                return;
            }

            if (sessionTitleEl) {
                sessionTitleEl.textContent = `Zarządzanie Sesją: ${htmlspecialchars(sessionDetailsForDisplay.title)} (ID: ${htmlspecialchars(sessionDetailsForDisplay.session_id)})`;
            }
            if (sessionStatusInfoEl) {
                let statusHtml = `<p>Status głównej sesji: <span class="badge badge-${mainSessionStatus === 'open' ? 'success' : 'secondary'}">${mainSessionStatus === 'open' ? 'Otwarta' : 'Zamknięta'}</span>`;
                if (sessionDetailsForDisplay.code) {
                    statusHtml += ` | Kod: <strong>${htmlspecialchars(sessionDetailsForDisplay.code)}</strong>`;
                }
                statusHtml += `</p>`;
                sessionStatusInfoEl.innerHTML = statusHtml;
            }

            if (resolutionsManagementArea) {
                 if (resolutionsForAdminList && resolutionsForAdminList.length > 0) {
                    renderAdminResolutionsList(resolutionsManagementArea, resolutionsForAdminList, mainSessionStatus);
                } else {
                    resolutionsManagementArea.innerHTML = '<p class="alert alert-info">Brak uchwał w tej sesji do zarządzania.</p>';
                }
            }

            // Zawsze próbuj renderować sekcję wyników/postępu, nawet jeśli jest pusta, aby usunąć "Ładowanie..."
            resultsDataDisplayArea.innerHTML = ''; // Wyczyść "Ładowanie..."
            progressResultsHeaderEl.textContent = ''; // Wyczyść stary nagłówek

            if (response.type === 'progress' && progressSpecificData) {
                progressResultsHeaderEl.textContent = "Ogólny Postęp Głosowania:";
                renderSessionProgress(resultsDataDisplayArea, progressSpecificData); 
            } else if (response.type === 'results' && resultsSpecificData) {
                progressResultsHeaderEl.textContent = "Ogólne Wyniki Głosowania:";
                renderSessionResults(resultsDataDisplayArea, resultsSpecificData); 
            } else {
                // Jeśli typ jest nieznany lub brakuje danych, wyświetl stosowny komunikat zamiast "Ładowanie..."
                progressResultsHeaderEl.textContent = "Informacje o Sesji:";
                resultsDataDisplayArea.innerHTML = `<p class="alert alert-light">Nie można obecnie wyświetlić szczegółowego postępu ani wyników (typ odpowiedzi: ${htmlspecialchars(response.type || 'nieznany')}).</p>`;
            }

            if (closeThisSessionBtn && mainSessionStatus === 'open') {
                closeThisSessionBtn.classList.remove('d-none');
            } else if (closeThisSessionBtn) { // Jeśli sesja nie jest open, ukryj przycisk zamknięcia
                closeThisSessionBtn.classList.add('d-none');
            }

        } else { 
            const message = response.message || 'Nie udało się załadować danych sesji.';
            displayMessage(resultsPageMessageElId, message, false);
            resultsDataDisplayArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(message)}</p>`;
            resolutionsManagementArea.innerHTML = '';
        }
    } catch (error) {
        console.error('loadResultsPageData: Error loading session data:', error);
        const errorMessage = error.message || 'Wystąpił krytyczny błąd podczas ładowania danych.';
        displayMessage(resultsPageMessageElId, errorMessage, false);
        resultsDataDisplayArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(errorMessage)}</p>`;
        resolutionsManagementArea.innerHTML = '';
    }
}

    function renderAdminResolutionsList(container, resolutions, mainSessionStatus) {
    console.log('renderAdminResolutionsList: Rendering resolutions for admin:', resolutions, 'Main session status:', mainSessionStatus);
    if (!resolutions || resolutions.length === 0) {
        container.innerHTML = '<p class="alert alert-info">Brak uchwał w tej sesji do zarządzania.</p>';
        return;
    }

    let html = '<ul class="list-group list-group-flush">'; 
    resolutions.forEach((resolution, index) => {
        const resNumber = resolution.number || (index + 1);
        let statusBadge = '';
        let actions = '';

        // Sprawdź, czy jakakolwiek inna uchwała jest już aktywna (jeśli obecna jest 'pending')
        // Ta logika jest też po stronie serwera, ale dla UI można to też uwzględnić
        const isAnotherResolutionActive = resolutions.some(r => r.resolution_id !== resolution.resolution_id && r.voting_status === 'active');

        switch (resolution.voting_status) {
            case 'pending':
                statusBadge = '<span class="badge badge-light">Oczekująca</span>';
                if (mainSessionStatus === 'open') { 
                    // Przycisk "Rozpocznij" jest aktywny tylko jeśli żadna inna uchwała nie jest aktualnie 'active'
                    actions = `<button class="btn btn-sm btn-success btn-start-resolution-vote" 
                                       data-resolution-id="${resolution.resolution_id}" 
                                       data-session-id="${currentResultsSessionId}" 
                                       ${isAnotherResolutionActive ? 'disabled title="Inna uchwała jest aktualnie aktywna"' : ''}>
                                   <i class="fas fa-play-circle"></i> Rozpocznij
                               </button>`;
                }
                break;
            case 'active':
                statusBadge = '<span class="badge badge-primary">Aktywne Głosowanie</span>';
                 if (mainSessionStatus === 'open') { // Teoretycznie, jeśli uchwała jest active, to sesja musi być open
                    actions = `<button class="btn btn-sm btn-warning btn-stop-resolution-vote" 
                                       data-resolution-id="${resolution.resolution_id}" 
                                       data-session-id="${currentResultsSessionId}">
                                   <i class="fas fa-stop-circle"></i> Zakończ
                               </button>`;
                }
                break;
            case 'closed':
                statusBadge = '<span class="badge badge-secondary">Zakończone</span>';
                // Brak akcji dla zakończonych uchwał (chyba że np. ponowne otwarcie, czego nie implementujemy)
                break;
            default:
                statusBadge = `<span class="badge badge-light">${htmlspecialchars(resolution.voting_status || 'Nieznany')}</span>`;
        }

        html += `
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap py-2 px-0">
                <div class="resolution-info mb-2 mb-md-0 flex-grow-1 mr-2">
                    <span class="font-weight-bold">Uchwała #${resNumber}:</span> ${htmlspecialchars(resolution.text)}<br>
                    <small>Status głosowania: ${statusBadge}</small>
                </div>
                <div class="resolution-actions btn-group" role="group">
                    ${actions}
                </div>
            </li>
        `;
    });
    html += '</ul>';
    container.innerHTML = html;

    container.querySelectorAll('.btn-start-resolution-vote').forEach(button => {
        button.addEventListener('click', handleStartResolutionVoteClick);
    });
    container.querySelectorAll('.btn-stop-resolution-vote').forEach(button => {
        button.addEventListener('click', handleStopResolutionVoteClick);
    });
}

    async function handleStartResolutionVoteClick(event) {
    const button = event.currentTarget;
    const resolutionId = button.dataset.resolutionId;
    const sessionId = button.dataset.sessionId; 
    if (!resolutionId || !sessionId) return;

    console.log(`Attempting to start voting for resolution ${resolutionId} in session ${sessionId}`);
    button.disabled = true; 
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rozpoczynanie...'; 
    displayMessage('results-page-message', `Rozpoczynanie głosowania nad uchwałą ID: ${resolutionId}...`, true);

    try {
        const response = await apiFetch('admin/start_resolution_voting.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ resolution_id: parseInt(resolutionId), session_id: parseInt(sessionId) })
        });
        if (response.status === 'success') {
            displayMessage('results-page-message', response.message + " Strona zostanie odświeżona.", true, 3500); // Komunikat z informacją o odświeżeniu
            // Odśwież stronę po krótkim opóźnieniu, aby użytkownik zobaczył komunikat
            setTimeout(() => {
                window.location.reload(); 
            }, 1500); // Opóźnienie 1.5 sekundy
        } else {
            displayMessage('results-page-message', response.message || 'Nie udało się rozpocząć głosowania.', false);
            button.disabled = false; 
            button.innerHTML = '<i class="fas fa-play-circle"></i> Rozpocznij głosowanie'; 
        }
    } catch (error) {
        displayMessage('results-page-message', `Błąd: ${htmlspecialchars(error.message)}`, false); // Użyj htmlspecialchars dla error.message
        button.disabled = false; 
        button.innerHTML = '<i class="fas fa-play-circle"></i> Rozpocznij głosowanie'; 
    }
}

    async function handleStopResolutionVoteClick(event) {
    const button = event.currentTarget;
    const resolutionId = button.dataset.resolutionId;
    const sessionId = button.dataset.sessionId;
    if (!resolutionId || !sessionId) return;

    console.log(`Attempting to stop voting for resolution ${resolutionId} in session ${sessionId}`);
    button.disabled = true; 
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Zakańczanie...'; 
    displayMessage('results-page-message', `Zakańczanie głosowania nad uchwałą ID: ${resolutionId}...`, true);
    
    try {
        const response = await apiFetch('admin/stop_resolution_voting.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ resolution_id: parseInt(resolutionId), session_id: parseInt(sessionId) })
        });
        if (response.status === 'success') {
            displayMessage('results-page-message', response.message + " Strona zostanie odświeżona.", true, 3500); // Komunikat z informacją o odświeżeniu
            // Odśwież stronę po krótkim opóźnieniu
            setTimeout(() => {
                window.location.reload();
            }, 1500); // Opóźnienie 1.5 sekundy
        } else {
            displayMessage('results-page-message', response.message || 'Nie udało się zakończyć głosowania.', false);
            button.disabled = false; 
            button.innerHTML = '<i class="fas fa-stop-circle"></i> Zakończ głosowanie'; 
        }
    } catch (error) {
        displayMessage('results-page-message', `Błąd: ${htmlspecialchars(error.message)}`, false); // Użyj htmlspecialchars dla error.message
        button.disabled = false; 
        button.innerHTML = '<i class="fas fa-stop-circle"></i> Zakończ głosowanie'; 
    }
}
    function renderSessionProgress(container, progressData) {
        console.log('renderSessionProgress: Rendering progress data:', progressData);
        if (!progressData) {
            container.innerHTML = '<p class="alert alert-warning">Brak danych o postępach.</p>';
            return;
        }
        const totalParticipantsLabel = progressData.total_unique_voting_sessions !== undefined ? 
            'Unikalne sesje głosujące:' : 
            'Liczba dołączonych (stare):';
        const totalParticipantsValue = progressData.total_unique_voting_sessions !== undefined ?
            progressData.total_unique_voting_sessions :
            (progressData.total_joined_participants || 0);

        const expectedVotes = (progressData.total_resolutions || 0) * totalParticipantsValue;
        const completionPercentage = (expectedVotes > 0 && progressData.total_votes_casted !== undefined) ? 
            ((progressData.total_votes_casted / expectedVotes) * 100).toFixed(1) : 0;

        let html = `
            <div class="content-card mt-3">
                <div class="card-header bg-light">
                    <h4 class="mb-0">Postęp Głosowania</h4>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Całkowita liczba uchwał:
                        <span class="badge badge-primary badge-pill">${progressData.total_resolutions || 0}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${totalParticipantsLabel}
                        <span class="badge badge-primary badge-pill">${totalParticipantsValue}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Sesje, które zagłosowały przynajmniej raz:
                        <span class="badge badge-info badge-pill">${progressData.sessions_voted_at_least_once || 0}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Sesje, które zagłosowały na wszystkie (aktywne/zamknięte) uchwały:
                        <span class="badge badge-success badge-pill">${progressData.sessions_voted_on_all_resolutions || 0}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Łączna liczba oddanych głosów:
                        <span class="badge badge-secondary badge-pill">${progressData.total_votes_casted || 0}</span>
                    </li>
                </ul>
                ${expectedVotes > 0 && progressData.total_votes_casted !== undefined ? `
                <div class="card-body">
                    <h5 class="card-title text-center mb-2">Procent Ukończenia Głosowania</h5>
                    <div class="progress mt-1" style="height: 25px; font-size: 0.9rem;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: ${completionPercentage}%;" aria-valuenow="${completionPercentage}" aria-valuemin="0" aria-valuemax="100">
                            ${completionPercentage}%
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        container.innerHTML = html;
    }

    function renderSessionResults(container, resultsData) {
        console.log('renderSessionResults: Rendering results data:', resultsData);
        if (!resultsData || !resultsData.resolutions_results) {
            container.innerHTML = '<p class="alert alert-warning">Brak danych o wynikach.</p>';
            return;
        }
        
        const totalParticipantsValue = resultsData.total_unique_voting_sessions !== undefined ? resultsData.total_unique_voting_sessions : 'B/D';

        let html = `
            <div class="content-card mt-3">
                <div class="card-header bg-light">
                    <h4 class="mb-0">Szczegółowe Wyniki Głosowania</h4>
                </div>
                <div class="card-body">
                    <p><strong>Liczba unikalnych sesji głosujących:</strong> ${totalParticipantsValue}</p>
        `;

        if (resultsData.resolutions_results.length === 0) {
            html += '<p class="alert alert-info mt-2">Brak uchwał w tej sesji.</p>';
        } else {
            html += `
                <div class="table-wrapper">
                <table class="table table-striped table-hover mt-3 table-responsive-stack">
                    <thead class="thead-light">
                        <tr>
                            <th>Lp.</th>
                            <th>Treść Uchwały</th>
                            <th class="text-center">Za</th>
                            <th class="text-center">Przeciw</th>
                            <th class="text-center">Wstrzymało się</th>
                            <th class="text-center">Łącznie Głosów</th>
                            <th class="text-center">Status Uchwały</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            resultsData.resolutions_results.forEach((res, index) => {
                const resNumber = res.number || (index + 1);
                const votesYes = res.results[Vote.CHOICE_YES] || 0;
                const votesNo = res.results[Vote.CHOICE_NO] || 0;
                const votesAbstain = res.results[Vote.CHOICE_ABSTAIN] || 0;
                const totalVotesOnResolution = res.results.total_votes || 0;
                
                let resolutionStatusText = 'Nierozstrzygnięta';
                let resolutionStatusClass = 'badge-secondary';

                if (totalVotesOnResolution > 0) { 
                    if (votesYes > votesNo) {
                        resolutionStatusText = 'Przyjęta';
                        resolutionStatusClass = 'badge-success';
                    } else if (votesNo > votesYes) {
                        resolutionStatusText = 'Odrzucona';
                        resolutionStatusClass = 'badge-danger';
                    } else { 
                         resolutionStatusText = 'Remis';
                         resolutionStatusClass = 'badge-warning';
                    }
                }

                html += `
                    <tr>
                        <td data-label="Lp.">${resNumber}</td>
                        <td data-label="Treść Uchwały" class="text-left">${htmlspecialchars(res.text)}</td>
                        <td data-label="Za" class="text-center">${votesYes}</td>
                        <td data-label="Przeciw" class="text-center">${votesNo}</td>
                        <td data-label="Wstrzymało się" class="text-center">${votesAbstain}</td>
                        <td data-label="Łącznie Głosów" class="text-center">${totalVotesOnResolution}</td>
                        <td data-label="Status Uchwały" class="text-center"><span class="badge ${resolutionStatusClass}">${resolutionStatusText}</span></td>
                    </tr>
                `;
            });
            html += `
                    </tbody>
                </table>
                </div>
            `;
        }
        html += `
                </div>
            </div>
        `;
        container.innerHTML = html;
    }

    async function handleCloseThisSessionClick(event) { 
    const button = event.currentTarget;
    const sessionId = button.dataset.sessionId;
    if (!sessionId) return;

    if (!confirm(`Czy na pewno chcesz zamknąć całą sesję głosowania o ID: ${sessionId}? Tej operacji nie można cofnąć.`)) {
        return;
    }
    
    const resultsPageMessageElId = 'results-page-message';
    displayMessage(resultsPageMessageElId, `Zamykanie sesji ID: ${sessionId}...`, true);
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Zamykanie...';


    try {
        const result = await apiFetch('admin/close_session.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: parseInt(sessionId) })
        });

        if (result.status === 'success') {
            // Zamiast odświeżać stronę wyników, przekieruj do panelu admina
            displayMessage(resultsPageMessageElId, (result.message || `Sesja ID: ${sessionId} została zamknięta.`) + " Przekierowywanie do panelu...", true, 4000); // Dłuższy czas na komunikat
            setTimeout(() => {
                window.location.href = 'admin_dashboard.php?info=session_closed_id_' + sessionId; // Przekierowanie
            }, 2000); // Opóźnienie na przeczytanie komunikatu
        } else {
            displayMessage(resultsPageMessageElId, result.message || 'Nie udało się zamknąć sesji.', false, 7000);
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-lock"></i> Zamknij Całą Sesję'; // Przywróć tekst
        }
    } catch (error) {
        displayMessage(resultsPageMessageElId, `Błąd podczas zamykania sesji: ${htmlspecialchars(error.message)}`, false, 7000);
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-lock"></i> Zamknij Całą Sesję'; // Przywróć tekst
    }
}
    
    // --- History Page Logic ---
    async function loadHistoryPageData() {
        console.log('loadHistoryPageData: Function triggered.');
        const historyListEl = document.getElementById('history-sessions-list');
        const historyPageMessageElId = 'history-page-message';

        if (!historyListEl) {
            console.log('loadHistoryPageData: History list element not found.');
            return;
        }
        displayMessage(historyPageMessageElId, '');
        historyListEl.innerHTML = '<p class="text-center">Ładowanie historii sesji...</p>';

        try {
            const response = await apiFetch('admin/list_sessions.php?status=closed');
            console.log('loadHistoryPageData: Response from list_sessions.php?status=closed:', response);

            if (response.status === 'success' && response.data) {
                renderHistorySessionsList(historyListEl, response.data);
            } else {
                const message = response.message || 'Nie udało się załadować historii sesji.';
                displayMessage(historyPageMessageElId, message, false);
                historyListEl.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(message)}</p>`;
            }
        } catch (error) {
            console.error('loadHistoryPageData: Error loading history data:', error);
            const errorMessage = error.message || 'Wystąpił błąd podczas ładowania historii.';
            displayMessage(historyPageMessageElId, errorMessage, false);
            historyListEl.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(errorMessage)}</p>`;
        }
    }

    function renderHistorySessionsList(containerElement, sessions) {
        console.log('renderHistorySessionsList: Rendering history sessions:', sessions);
        if (!sessions || sessions.length === 0) {
            containerElement.innerHTML = '<p class="alert alert-info mt-3">Brak zakończonych sesji głosowań w historii.</p>';
            return;
        }

        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            try {
                return new Date(dateString).toLocaleDateString('pl-PL', {
                    year: 'numeric', month: 'long', day: 'numeric',
                    hour: '2-digit', minute: '2-digit' 
                });
            } catch (e) {
                console.warn("Error formatting date:", dateString, e);
                return dateString; 
            }
        };

        let html = `
            <div class="table-wrapper">
            <table class="table table-striped table-hover mt-2 table-responsive-stack">
                <thead class="thead-light">
                    <tr>
                        <th>Nazwa Sesji (ID)</th>
                        <th>Data Utworzenia</th>
                        <th>Data Zamknięcia</th>
                        <th>Kod</th>
                        <th class="text-center">Uchwały</th>
                        <th class="text-center">Akcje</th>
                    </tr>
                </thead>
                <tbody>
        `;
        sessions.forEach(session => {
            const createdAtDate = formatDate(session.created_at);
            const closedAtDate = formatDate(session.closed_at); 

            html += `
                <tr>
                    <td data-label="Nazwa Sesji">${htmlspecialchars(session.title)} (ID: ${session.session_id})</td>
                    <td data-label="Data Utworzenia">${createdAtDate}</td>
                    <td data-label="Data Zamknięcia">${closedAtDate}</td>
                    <td data-label="Kod">${htmlspecialchars(session.code)}</td>
                    <td data-label="Uchwały" class="text-center">${session.resolutions ? session.resolutions.length : '0'}</td>
                    <td data-label="Akcje" class="text-center">
                        <a href="results.php?session_id=${session.session_id}" class="btn btn-sm btn-info" title="Zobacz szczegółowe wyniki">
                            <i class="fas fa-poll-h"></i> Wyniki
                        </a>
                    </td>
                </tr>
            `;
        });
        html += `
                </tbody>
            </table>
            </div>
        `;
        containerElement.innerHTML = html;
    }


    // --- Page Specific Initializations & Event Listeners ---

        const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    if (window.location.pathname.endsWith('logout.php')) {
        handleLogout(true); 
    }

    const createSessionPageForm = document.getElementById('create-session-form-page');
    if (createSessionPageForm) {
        createSessionPageForm.addEventListener('submit', handleCreateSession);
    }

    // Admin Dashboard Page Initialization
    if (document.getElementById('active-sessions-list') && document.getElementById('closed-sessions-list')) {
        console.log('scripts.js: On admin dashboard page, calling loadAdminDashboardData.');
        loadAdminDashboardData();
    }

    // Join Session Page Initialization
    const joinSessionForm = document.getElementById('join-session-form');
    if (joinSessionForm) {
        console.log('scripts.js: Join session form found, adding submit event listener.');
        joinSessionForm.addEventListener('submit', handleJoinSession);
        
        const sessionCodeInput = joinSessionForm.querySelector('input[name="session_code"]');
        const savedSessionCode = loadFromLocalStorage('last_joined_session_code');
        if (sessionCodeInput && savedSessionCode) {
            sessionCodeInput.value = savedSessionCode;
        }
        if (sessionCodeInput) {
            sessionCodeInput.addEventListener('input', function() {
                saveToLocalStorage('last_joined_session_code', this.value.trim().toUpperCase());
            });
        }
    }
    
    // Vote Page Initialization
    const resolutionsVotingArea = document.getElementById('resolutions-voting-area');
    if (resolutionsVotingArea) { 
        console.log('scripts.js: On vote page, calling loadVotingPageData.');
        loadVotingPageData();

        const refreshVoteDataBtn = document.getElementById('refresh-vote-data-btn');
        if (refreshVoteDataBtn) {
            refreshVoteDataBtn.addEventListener('click', loadVotingPageData);
        }
    }
    
    // Results Page Initialization (ZMIENIONA LOGIKA PRZYCISKU ODŚWIEŻANIA)
    const resultsDataArea = document.getElementById('session-data-area'); 
    if (resultsDataArea && window.location.pathname.includes('results.php')) { 
        console.log('scripts.js: On results page, calling loadResultsPageData for initial load.');
        loadResultsPageData(); // Ładuj dane przy pierwszym wejściu

        const refreshBtn = document.getElementById('refresh-results-btn');
        if (refreshBtn) {
            console.log('scripts.js: Refresh button found on results page, adding click listener for full page reload.');
            refreshBtn.addEventListener('click', function(event) { 
                event.preventDefault(); 
                console.log('Refresh button clicked on results page - reloading page.');
                window.location.reload(); // PRZEŁADUJ CAŁĄ STRONĘ
            });
        }
        const closeThisSessionBtn = document.getElementById('close-this-session-btn');
        if (closeThisSessionBtn) {
            closeThisSessionBtn.addEventListener('click', handleCloseThisSessionClick);
        }
    }

    // History Page Initialization
    if (document.getElementById('history-sessions-list') && window.location.pathname.includes('history.php')) {
        console.log('scripts.js: On history page, calling loadHistoryPageData.');
        loadHistoryPageData();
    }


    // Call on every page load (poza logout.php) to check admin login status and update UI
    if (!window.location.pathname.endsWith('logout.php')) {
        console.log('scripts.js: Not on logout.php, calling checkLoginStatusAndUpdateNav.');
        checkLoginStatusAndUpdateNav();
    }

    // --- LocalStorage Helper Functions ---
    function saveToLocalStorage(key, data) { 
        try {
            localStorage.setItem(LS_PREFIX + key, JSON.stringify(data));
        } catch (e) { console.error("Error saving to localStorage", e); }
    }
    function loadFromLocalStorage(key) { 
        try {
            const data = localStorage.getItem(LS_PREFIX + key);
            return data ? JSON.parse(data) : null;
        } catch (e) { console.error("Error loading from localStorage", e); return null; }
    }
    function removeFromLocalStorage(key) { 
        try {
            localStorage.removeItem(LS_PREFIX + key);
        } catch (e) { console.error("Error removing from localStorage", e); }
    }

    // --- Create Session Page - Form State Management with LocalStorage ---
    const resolutionsListContainer = document.getElementById('resolutions-list');  

    function saveCreateSessionFormState() { 
        if (!createSessionPageForm || !document.body.contains(createSessionPageForm)) {
            return;
        }
        const sessionTitleInput = createSessionPageForm.querySelector('input[name="session_title"]'); 
        const resolutions = [];
        if (resolutionsListContainer) {
            const resolutionTextareas = resolutionsListContainer.querySelectorAll('textarea[name^="resolutions_texts"]');
            resolutionTextareas.forEach(textarea => {
                resolutions.push(textarea.value);
            });
        }
        const dataToSave = {
            title: sessionTitleInput ? sessionTitleInput.value : '', 
            resolutions: resolutions
        };
        saveToLocalStorage('create_session_form_data', dataToSave);
    }

    function loadCreateSessionFormState() { 
        if (!createSessionPageForm || !document.body.contains(createSessionPageForm) || !resolutionsListContainer) {
            return;
        }
        const savedData = loadFromLocalStorage('create_session_form_data');
        const sessionTitleInput = createSessionPageForm.querySelector('input[name="session_title"]');
        if (sessionTitleInput && savedData && typeof savedData.title !== 'undefined') { 
            sessionTitleInput.value = savedData.title;
        }

        if (resolutionsListContainer) { 
            resolutionsListContainer.innerHTML = ''; 
            if (savedData && savedData.resolutions && savedData.resolutions.length > 0) {
                savedData.resolutions.forEach((resText) => {
                    addResolutionFieldToFormInternal(resText, false); 
                });
            } else { 
                 addResolutionFieldToFormInternal('', false); 
            }
        }
        updateRemoveResolutionButtonsState(); 
        updateResolutionFieldNumbers(); 
    }
    
    function addResolutionFieldToFormInternal(resolutionText = '', fromButtonClick = true) { 
        if (!resolutionsListContainer || !document.body.contains(resolutionsListContainer)) return;
        
        const currentResolutionIndex = resolutionsListContainer.children.length; 
        const displayResolutionNumber = currentResolutionIndex + 1;

        const newResolutionDiv = document.createElement('div');
        newResolutionDiv.classList.add('resolution-input-group', 'content-card', 'mb-3'); 
        newResolutionDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center p-2" style="background-color: var(--light-bg); border-bottom: 1px solid var(--border-color);">
                <h5 class="mb-0" style="font-size: 1rem;">Uchwała #${displayResolutionNumber}</h5>
                <button type="button" class="btn btn-sm btn-danger btn-remove-resolution"><i class="fas fa-trash-alt"></i> Usuń</button>
            </div>
            <div class="form-group p-2 mb-0">
                <label for="resolution_text_${currentResolutionIndex}" class="sr-only">Treść uchwały #${displayResolutionNumber}:</label>
                <textarea name="resolutions_texts[]" id="resolution_text_${currentResolutionIndex}" class="form-control" rows="3" required placeholder="Wpisz treść uchwały...">${htmlspecialchars(resolutionText)}</textarea>
            </div>
        `;
        resolutionsListContainer.appendChild(newResolutionDiv);
        updateRemoveResolutionButtonsState();
        if (fromButtonClick) {
            saveCreateSessionFormState(); 
        }
    }

    function updateRemoveResolutionButtonsState() { 
        if (!resolutionsListContainer || !document.body.contains(resolutionsListContainer)) return;
        const removeButtons = resolutionsListContainer.querySelectorAll('.btn-remove-resolution');
        removeButtons.forEach(button => {
            button.removeEventListener('click', handleRemoveResolutionField); 
            button.addEventListener('click', handleRemoveResolutionField);
        });

        if (resolutionsListContainer.children.length <= 1) {
            const firstRemoveBtn = resolutionsListContainer.querySelector('.btn-remove-resolution');
            if (firstRemoveBtn) firstRemoveBtn.style.display = 'none';
        } else {
             const allRemoveBtns = resolutionsListContainer.querySelectorAll('.btn-remove-resolution');
             allRemoveBtns.forEach(btn => btn.style.display = 'inline-flex'); 
        }
    }

    function handleRemoveResolutionField(event) { 
        if (!resolutionsListContainer || !document.body.contains(resolutionsListContainer)) return;
        if (resolutionsListContainer.children.length > 1) {
            event.target.closest('.resolution-input-group').remove();
            updateResolutionFieldNumbers(); 
            saveCreateSessionFormState();
            updateRemoveResolutionButtonsState();
        } else {
            alert('Musi pozostać przynajmniej jedna uchwała.');
        }
    }
    
    function updateResolutionFieldNumbers() { 
        if (!resolutionsListContainer || !document.body.contains(resolutionsListContainer)) return;
        const resolutionGroups = resolutionsListContainer.querySelectorAll('.resolution-input-group');
        resolutionGroups.forEach((group, index) => {
            const titleElement = group.querySelector('h5'); 
            const labelElement = group.querySelector('label'); 
            const textareaElement = group.querySelector('textarea');
            const displayIndex = index + 1; 
            const actualIndex = index;      

            if (titleElement) titleElement.textContent = `Uchwała #${displayIndex}`;
            if (labelElement) {
                labelElement.setAttribute('for', `resolution_text_${actualIndex}`);
                labelElement.textContent = `Treść uchwały #${displayIndex}:`;
            }
            if (textareaElement) {
                textareaElement.id = `resolution_text_${actualIndex}`;
            }
        });
    }

    if (createSessionPageForm && resolutionsListContainer) {
        console.log('scripts.js: Initializing create session page form state management.');
        loadCreateSessionFormState(); 
        createSessionPageForm.addEventListener('input', function(event) {
            if (event.target.name === 'session_title' || event.target.name === 'resolutions_texts[]') {
                saveCreateSessionFormState();
            }
        });
        const addResolutionButton = document.getElementById('add-resolution-btn');
        if (addResolutionButton) {
            addResolutionButton.addEventListener('click', function() {
                addResolutionFieldToFormInternal('', true); 
            });
        }
    }
    console.log('scripts.js: End of script execution.');
});