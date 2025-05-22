document.addEventListener('DOMContentLoaded', function() {
    console.log('scripts.js loaded and DOMContentLoaded event fired'); 

    const API_BASE_URL = 'api/'; 

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
    
    // Definicja stałych Vote, jeśli potrzebne w JS (np. dla renderSessionResults)
    const Vote = {
        CHOICE_YES: 'tak',
        CHOICE_NO: 'nie',
        CHOICE_ABSTAIN: 'wstrzymanie'
    };


    // --- Authentication Logic ---
    async function handleLogin(event) {
        console.log('handleLogin: Function triggered.'); 
        event.preventDefault(); 
        console.log('handleLogin: event.preventDefault() called.');

        const form = event.target;
        const emailInput = form.querySelector('input[name="email"]'); 
        const passwordInput = form.querySelector('input[name="password"]');
        const messageElId = 'login-message';

        if (!emailInput || !passwordInput) {
            console.error('handleLogin: Email or password input not found in the form.');
            displayMessage(messageElId, 'Błąd formularza: brak pól email lub hasła.', false);
            return;
        }

        const email = emailInput.value;
        const password = passwordInput.value;
        console.log(`handleLogin: Email: '${email}', Password: (hidden)`);

        displayMessage(messageElId, ''); 

        try {
            console.log('handleLogin: Attempting to call apiFetch for login_endpoint.php');
            const data = await apiFetch('login_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            console.log('handleLogin: Data received from login_endpoint.php:', data);

            if (data.status === 'success') {
                displayMessage(messageElId, data.message, true);
                if (data.user) {
                    saveToLocalStorage('currentUser', data.user);
                    console.log('handleLogin: User data saved to LocalStorage.');
                }
                if (data.redirectUrl) {
                    console.log(`handleLogin: Redirecting to ${data.redirectUrl}`);
                    window.location.href = data.redirectUrl;
                } else {
                    console.warn('handleLogin: No redirectUrl received, updating navigation.');
                    updateNavigation(data.user);
                }
            } else {
                displayMessage(messageElId, data.message || 'Logowanie nie powiodło się.', false);
            }
        } catch (error) {
            console.error('handleLogin: Error during login process:', error);
            displayMessage(messageElId, error.message || 'Wystąpił błąd podczas logowania.', false);
        }
    }

    async function handleLogout(redirect = true) {
        console.log('handleLogout: Function triggered.');
        const logoutPageMessageEl = document.getElementById('logout-page-message');
        const logoutSpinnerEl = document.getElementById('logout-spinner');
        const manualRedirectLinkEl = document.getElementById('manual-redirect-link');

        if (logoutSpinnerEl) logoutSpinnerEl.style.display = 'block';
        if (logoutPageMessageEl) logoutPageMessageEl.textContent = 'Wylogowywanie, proszę czekać...';

        try {
            const data = await apiFetch('logout_endpoint.php', { method: 'POST' });
            console.log('handleLogout: Data received from logout_endpoint.php:', data);
            if (data.status === 'success') {
                if (logoutPageMessageEl) logoutPageMessageEl.textContent = data.message || 'Wylogowano pomyślnie.';
                removeFromLocalStorage('currentUser'); 
                removeFromLocalStorage('currentVotingSession'); 
                removeFromLocalStorage('last_joined_session_code'); 
                updateNavigation(null); 

                if (redirect) {
                    if (logoutPageMessageEl) logoutPageMessageEl.textContent += ' Za chwilę zostaniesz przekierowany.';
                    setTimeout(() => {
                        console.log('handleLogout: Redirecting to index.php after logout.');
                        window.location.href = 'index.php';
                    }, 1500); 
                }
            } else {
                if (logoutPageMessageEl) logoutPageMessageEl.textContent = data.message || 'Nie udało się wylogować po stronie serwera, ale sesja frontendu została wyczyszczona.';
                removeFromLocalStorage('currentUser');
                removeFromLocalStorage('currentVotingSession');
                removeFromLocalStorage('last_joined_session_code');
                updateNavigation(null);
                if (manualRedirectLinkEl) manualRedirectLinkEl.classList.remove('d-none');
            }
        } catch (error) {
            console.error('handleLogout: Error during logout process:', error);
            if (logoutPageMessageEl) logoutPageMessageEl.textContent = error.message || 'Wystąpił błąd podczas wylogowywania.';
            removeFromLocalStorage('currentUser');
            removeFromLocalStorage('currentVotingSession');
            removeFromLocalStorage('last_joined_session_code');
            updateNavigation(null);
            if (manualRedirectLinkEl) manualRedirectLinkEl.classList.remove('d-none');
            if (redirect) { 
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 3000);
            }
        } finally {
            if (logoutSpinnerEl) logoutSpinnerEl.style.display = 'none';
        }
    }


    async function checkLoginStatusAndUpdateNav() {
        console.log('checkLoginStatusAndUpdateNav: Function triggered.');
        let user = loadFromLocalStorage('currentUser'); 
        console.log('checkLoginStatusAndUpdateNav: User from LocalStorage:', user);

        if (!user) { 
            console.log('checkLoginStatusAndUpdateNav: No user in LS, fetching from server.');
            try {
                const data = await apiFetch('check_session.php');
                console.log('checkLoginStatusAndUpdateNav: Data from check_session.php:', data);
                if (data.loggedIn && data.user) {
                    user = data.user;
                    saveToLocalStorage('currentUser', user);
                    console.log('checkLoginStatusAndUpdateNav: User data from server saved to LS.');
                } else {
                    removeFromLocalStorage('currentUser'); 
                }
            } catch (error) {
                console.warn('checkLoginStatusAndUpdateNav: Failed to check session status with server:', error);
                removeFromLocalStorage('currentUser');
                user = null; 
            }
        }
        
        updateNavigation(user);

        const currentPage = window.location.pathname.split('/').pop() || 'index.php'; 
        const authRequiredPages = ['admin_dashboard.php', 'create_session.php', 'history.php', 'results.php', 'vote.php'];
        const teacherOnlyPages = ['vote.php']; 
        console.log(`checkLoginStatusAndUpdateNav: Current page: ${currentPage}, User:`, user);

        if (user) {
            let dashboardUrl = 'join_session.php'; 
            if (user.role === 'admin') {
                dashboardUrl = 'admin_dashboard.php';
            }
            if ((currentPage === 'index.php') && !window.location.pathname.endsWith(dashboardUrl)) {
                 console.log(`checkLoginStatusAndUpdateNav: User logged in and on index.php, redirecting to ${dashboardUrl}`);
                 window.location.href = dashboardUrl;
            }
            if (user.role === 'admin' && teacherOnlyPages.includes(currentPage)) {
                console.log(`checkLoginStatusAndUpdateNav: Admin on teacher-only page, redirecting to admin_dashboard.php.`);
                window.location.href = 'admin_dashboard.php';
            }
        } else { 
            if (authRequiredPages.includes(currentPage) || currentPage === 'join_session.php') {
                console.log(`checkLoginStatusAndUpdateNav: User not logged in and on auth-required page ('${currentPage}'), redirecting to index.php.`);
                window.location.href = 'index.php?redirect_message=access_denied_for_' + currentPage; 
            }
        }
    }

    function updateNavigation(user) {
        console.log('updateNavigation: Updating navigation for user:', user);
        const nav = document.getElementById('main-navigation');
        if (!nav) {
            console.error('updateNavigation: Navigation element with ID "main-navigation" not found.');
            return;
        }

        let navLinks = '';
        if (user) { 
            if (user.role === 'admin') {
                navLinks += '<a href="admin_dashboard.php">Panel Dyrektora</a>';
                navLinks += '<a href="create_session.php">Utwórz Sesję</a>';
                navLinks += '<a href="history.php">Historia Głosowań</a>';
                        } else if (user.role === 'teacher') {
                navLinks += '<a href="join_session.php">Dołącz do Sesji</a>';
                const activeVotingSession = loadFromLocalStorage('currentVotingSession');
                if (activeVotingSession && activeVotingSession.sessionId) {
                     navLinks += `<a href="vote.php?session_id=${activeVotingSession.sessionId}">Aktywne Głosowanie</a>`;
                }
            }
            const roleDisplay = user.role === 'teacher' ? 'Nauczyciel' : htmlspecialchars(user.role); // Zmiana wyświetlania roli
            navLinks += `<span style="color: #fff; margin-left: 15px; margin-right:15px; font-size: 0.9em;">Zalogowano: ${htmlspecialchars(user.name)} (${roleDisplay})</span>`;
            navLinks += '<a href="logout.php" class="nav-link-logout" id="logout-link">Wyloguj</a>';
        } else { 
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            if (currentPage !== 'index.php') { 
                navLinks += '<a href="index.php">Logowanie</a>';
            }
             navLinks += '<a href="join_session.php">Dołącz do Sesji (Nauczyciel)</a>';
        }
        nav.innerHTML = navLinks;
        console.log('updateNavigation: Navigation HTML set.');

        const logoutLink = document.getElementById('logout-link');
        if (logoutLink) {
            console.log('updateNavigation: Logout link found, adding event listener.');
            logoutLink.addEventListener('click', function(event) {
                event.preventDefault(); 
                console.log('updateNavigation: Logout link clicked.');
                handleLogout(true);     
            });
        } else {
            console.log('updateNavigation: Logout link not found (this is normal if user is not logged in).');
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
                <li class="list-group-item session-item-admin card mb-3 p-3">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1 card-title">${htmlspecialchars(session.title)} (ID: ${session.session_id})</h5>
                        <small class="text-muted">Utworzono: ${createdAtDate}</small>
                    </div>
                    <p class="mb-1 card-text">Kod dostępu: <strong>${htmlspecialchars(session.code)}</strong> | Status: <span class="badge ${statusBadgeClass}">${statusText}</span></p>
                    <p class="mb-1 card-text">Liczba uchwał: ${session.resolutions ? session.resolutions.length : 'B/D'}</p>
                    <div class="mt-2 session-actions">
                        ${session.status === 'open' ? 
                            `<button class="btn btn-sm btn-danger btn-close-session mr-2" data-session-id="${session.session_id}">Zamknij Sesję</button>
                             <a href="results.php?session_id=${session.session_id}" class="btn btn-sm btn-primary">Zobacz Postęp / Głosy</a>` :
                            `<a href="results.php?session_id=${session.session_id}" class="btn btn-sm btn-info">Zobacz Wyniki</a>`
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
        const sessionId = event.target.dataset.sessionId;
        if (!sessionId) return;

        if (!confirm(`Czy na pewno chcesz zamknąć sesję o ID: ${sessionId}? Tej operacji nie można cofnąć.`)) {
            return;
        }
        
        const dashboardMessageElId = 'dashboard-message'; // Użyj tego samego ID co dla innych komunikatów na dashboardzie
        displayMessage(dashboardMessageElId, `Zamykanie sesji ID: ${sessionId}...`, true);

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
            }
        } catch (error) {
            displayMessage(dashboardMessageElId, `Błąd podczas zamykania sesji: ${htmlspecialchars(error.message)}`, false, 7000);
        }
    }

    // --- Join Session Logic ---
    async function handleJoinSession(event) {
        event.preventDefault();
        console.log('handleJoinSession: Function triggered.');
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
            return;
        }
        
        console.log(`handleJoinSession: Attempting to join session with code: ${sessionCode}`);

        try {
            const data = await apiFetch('join_session_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_code: sessionCode })
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
                    console.log('handleJoinSession: Voting session data saved to LocalStorage.');
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
    let currentVotingSessionData = null; 

    async function loadVotingPageData() {
        console.log('loadVotingPageData: Function triggered.');
        const resolutionsArea = document.getElementById('resolutions-voting-area');
        const sessionTitleEl = document.getElementById('vote-session-title');
        const votePageMessageElId = 'vote-page-message';
        const finishVotingBtn = document.getElementById('finish-voting-btn');

        if (!resolutionsArea || !sessionTitleEl) {
            console.log('loadVotingPageData: Not on vote page or missing key elements.');
            return;
        }
        displayMessage(votePageMessageElId, '');
        resolutionsArea.innerHTML = '<p class="text-center">Ładowanie danych sesji głosowania...</p>';

        try {
            const response = await apiFetch('get_session_for_voting.php'); 
            console.log('loadVotingPageData: Response from get_session_for_voting.php:', response);

            if (response.status === 'success' && response.data && response.data.session) {
                currentVotingSessionData = response.data; 
                const session = response.data.session;

                if (sessionTitleEl) {
                    sessionTitleEl.textContent = `Panel Głosowania: ${htmlspecialchars(session.title)}`;
                }
                renderResolutionsForVoting(resolutionsArea, session.resolutions || []);
                
                if (finishVotingBtn) {
                    finishVotingBtn.classList.remove('d-none');
                    finishVotingBtn.onclick = () => { 
                        removeFromLocalStorage('currentVotingSession');
                        console.log('finish-voting-btn: Clicked, redirecting to join_session.php');
                        window.location.href = 'join_session.php?info=voting_panel_left';
                    };
                }

            } else {
                const message = response.message || 'Nie udało się załadować danych sesji głosowania.';
                displayMessage(votePageMessageElId, message, false);
                resolutionsArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(message)}</p>`;
                if (response.action === 'redirect_to_join') {
                    setTimeout(() => { window.location.href = 'join_session.php?reason=session_data_load_failed'; }, 3000);
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
            if (error.message.toLowerCase().includes('wymagane logowanie') || error.message.toLowerCase().includes('nie jesteś aktualnie w żadnej sesji')) {
                setTimeout(() => { window.location.href = 'join_session.php?reason=session_ended_or_error'; }, 3000);
            }
        }
    }

    function renderResolutionsForVoting(container, resolutions) {
        console.log('renderResolutionsForVoting: Rendering resolutions:', resolutions);
        if (!resolutions || resolutions.length === 0) {
            container.innerHTML = '<p class="alert alert-info">W tej sesji nie ma żadnych uchwał do głosowania.</p>';
            return;
        }

        let html = '';
        resolutions.forEach((resolution, index) => {
            const resolutionNumber = resolution.number || (index + 1);
            const hasVoted = resolution.voted_choice !== null;
            const votedChoice = resolution.voted_choice;

            html += `
                <div class="resolution-item card mb-3" id="resolution-item-${resolution.resolution_id}">
                    <div class="card-body">
                        <h4 class="card-title">Uchwała #${resolutionNumber}: ${htmlspecialchars(resolution.text)}</h4>
                        <div id="vote-options-${resolution.resolution_id}" class="vote-options mt-3">
                            <button class="btn btn-success vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="tak" ${hasVoted && votedChoice === 'tak' ? 'disabled' : ''} ${hasVoted && votedChoice !== 'tak' && votedChoice !== null ? 'style="opacity:0.5;"' : ''}>
                                <i class="fas fa-check"></i> Tak ${hasVoted && votedChoice === 'tak' ? '(Twój głos)' : ''}
                            </button>
                            <button class="btn btn-danger vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="nie" ${hasVoted && votedChoice === 'nie' ? 'disabled' : ''} ${hasVoted && votedChoice !== 'nie' && votedChoice !== null ? 'style="opacity:0.5;"' : ''}>
                                <i class="fas fa-times"></i> Nie ${hasVoted && votedChoice === 'nie' ? '(Twój głos)' : ''}
                            </button>
                            <button class="btn btn-warning vote-btn" data-resolution-id="${resolution.resolution_id}" data-choice="wstrzymanie" ${hasVoted && votedChoice === 'wstrzymanie' ? 'disabled' : ''} ${hasVoted && votedChoice !== 'wstrzymanie' && votedChoice !== null ? 'style="opacity:0.5;"' : ''}>
                                <i class="fas fa-pause-circle"></i> Wstrzymuję się ${hasVoted && votedChoice === 'wstrzymanie' ? '(Twój głos)' : ''}
                            </button>
                        </div>
                        <div id="vote-status-${resolution.resolution_id}" class="mt-2" style="font-size: 0.9em; color: green;">
                            ${hasVoted ? `Zagłosowano: ${votedChoice === 'tak' ? 'Na Tak' : (votedChoice === 'nie' ? 'Na Nie' : 'Wstrzymano się')}` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
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
        const votePageMessageElId = 'vote-page-message'; 
        const resolutionItemStatusEl = document.getElementById(`vote-status-${resolutionId}`);

        console.log(`handleVoteButtonClick: resolutionId=${resolutionId}, choice=${choice}`);
        displayMessage(votePageMessageElId, ''); 

        const optionsDiv = document.getElementById(`vote-options-${resolutionId}`);
        if (optionsDiv) {
            optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                btn.disabled = true; 
                btn.style.opacity = '0.5'; 
            });
            button.style.opacity = '1'; 
        }
        if (resolutionItemStatusEl) resolutionItemStatusEl.textContent = 'Przetwarzanie głosu...';


        try {
            const response = await apiFetch('vote_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ resolution_id: parseInt(resolutionId), choice: choice })
            });
            console.log('handleVoteButtonClick: Response from vote_endpoint.php:', response);

            if (response.status === 'success' && response.data) {
            if (resolutionItemStatusEl) {
                    resolutionItemStatusEl.textContent = `Twój głos (${htmlspecialchars(response.data.choice)}) został pomyślnie zapisany.`; // Ulepszony tekst
                    resolutionItemStatusEl.style.color = 'green';
                }

                if (optionsDiv) {
                    optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                        const btnChoice = btn.dataset.choice;
                        const isCurrentVote = btnChoice === response.data.choice;
                        
                        btn.disabled = isCurrentVote; 
                        btn.style.opacity = isCurrentVote ? '1' : '0.5'; 
                        
                        let baseText = '';
                        if (btnChoice === 'tak') baseText = 'Tak';
                        else if (btnChoice === 'nie') baseText = 'Nie';
                        else if (btnChoice === 'wstrzymanie') baseText = 'Wstrzymuję się';
                        
                        let iconClass = '';
                        if (btnChoice === 'tak') iconClass = 'fa-check';
                        else if (btnChoice === 'nie') iconClass = 'fa-times';
                        else if (btnChoice === 'wstrzymanie') iconClass = 'fa-pause-circle';

                        btn.innerHTML = `<i class="fas ${iconClass}"></i> ${baseText} ${isCurrentVote ? '(Twój głos)' : ''}`;
                    });
                }
                
                if (currentVotingSessionData && currentVotingSessionData.session && currentVotingSessionData.session.resolutions) {
                    const resIndex = currentVotingSessionData.session.resolutions.findIndex(r => r.resolution_id == resolutionId);
                    if (resIndex > -1) {
                        currentVotingSessionData.session.resolutions[resIndex].voted_choice = response.data.choice;
                    }
                }

            } else {
                const message = response.message || 'Nie udało się zapisać głosu.';
                if (resolutionItemStatusEl) {
                    resolutionItemStatusEl.textContent = message;
                    resolutionItemStatusEl.style.color = 'red';
                }
                displayMessage(votePageMessageElId, message, false);
                if (optionsDiv) {
                    optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                        const resolutionData = currentVotingSessionData?.session?.resolutions.find(r => r.resolution_id == resolutionId);
                        const previousChoice = resolutionData?.voted_choice;
                        const isPreviousVote = btn.dataset.choice === previousChoice;

                        btn.disabled = isPreviousVote && previousChoice !== null;
                        btn.style.opacity = (isPreviousVote && previousChoice !== null) || previousChoice === null ? '1' : '0.5';

                        let baseText = '';
                        if (btn.dataset.choice === 'tak') baseText = 'Tak';
                        else if (btn.dataset.choice === 'nie') baseText = 'Nie';
                        else if (btn.dataset.choice === 'wstrzymanie') baseText = 'Wstrzymuję się';
                        
                        let iconClass = '';
                        if (btn.dataset.choice === 'tak') iconClass = 'fa-check';
                        else if (btn.dataset.choice === 'nie') iconClass = 'fa-times';
                        else if (btn.dataset.choice === 'wstrzymanie') iconClass = 'fa-pause-circle';

                        btn.innerHTML = `<i class="fas ${iconClass}"></i> ${baseText} ${isPreviousVote && previousChoice !== null ? '(Twój głos)' : ''}`;
                    });
                }
            }

        } catch (error) {
            console.error('handleVoteButtonClick: Error during voting:', error);
            const errorMessage = error.message || 'Wystąpił błąd podczas zapisywania głosu.';
            if (resolutionItemStatusEl) {
                resolutionItemStatusEl.textContent = errorMessage;
                resolutionItemStatusEl.style.color = 'red';
            }
            displayMessage(votePageMessageElId, errorMessage, false);
            if (optionsDiv) { 
                 optionsDiv.querySelectorAll('.vote-btn').forEach(btn => {
                    const resolutionData = currentVotingSessionData?.session?.resolutions.find(r => r.resolution_id == resolutionId);
                    const previousChoice = resolutionData?.voted_choice;
                    const isPreviousVote = btn.dataset.choice === previousChoice;

                    btn.disabled = isPreviousVote && previousChoice !== null;
                    btn.style.opacity = (isPreviousVote && previousChoice !== null) || previousChoice === null ? '1' : '0.5';
                    
                    let baseText = '';
                    if (btn.dataset.choice === 'tak') baseText = 'Tak';
                    else if (btn.dataset.choice === 'nie') baseText = 'Nie';
                    else if (btn.dataset.choice === 'wstrzymanie') baseText = 'Wstrzymuję się';
                    
                    let iconClass = '';
                    if (btn.dataset.choice === 'tak') iconClass = 'fa-check';
                    else if (btn.dataset.choice === 'nie') iconClass = 'fa-times';
                    else if (btn.dataset.choice === 'wstrzymanie') iconClass = 'fa-pause-circle';

                    btn.innerHTML = `<i class="fas ${iconClass}"></i> ${baseText} ${isPreviousVote && previousChoice !== null ? '(Twój głos)' : ''}`;
                });
            }
        }
    }

    // --- Results Page Logic ---
    let currentResultsSessionId = null; 

    async function loadResultsPageData() {
        console.log('loadResultsPageData: Function triggered.');
        const resultsArea = document.getElementById('session-data-area');
        const sessionTitleEl = document.getElementById('results-session-title');
        const resultsPageMessageElId = 'results-page-message';
        const sessionStatusInfoEl = document.getElementById('session-status-info');
        const closeThisSessionBtn = document.getElementById('close-this-session-btn');

        const urlParams = new URLSearchParams(window.location.search);
        currentResultsSessionId = urlParams.get('session_id');

        if (!currentResultsSessionId) {
            displayMessage(resultsPageMessageElId, 'Brak ID sesji w URL.', false);
            if (resultsArea) resultsArea.innerHTML = '<p class="alert alert-danger">Nie określono ID sesji.</p>';
            return;
        }
        
        if (!resultsArea || !sessionTitleEl || !sessionStatusInfoEl) {
            console.log('loadResultsPageData: Not on results page or missing key elements.');
            return;
        }

        displayMessage(resultsPageMessageElId, '');
        resultsArea.innerHTML = '<p class="text-center">Ładowanie danych...</p>';
        sessionStatusInfoEl.innerHTML = '';
        if (closeThisSessionBtn) closeThisSessionBtn.classList.add('d-none');


        try {
            const response = await apiFetch(`admin/get_session_results.php?session_id=${currentResultsSessionId}`);
            console.log('loadResultsPageData: Response from get_session_results.php:', response);

            if (response.status === 'success' && response.data) {
                // Dane sesji mogą być w response.data (dla wyników) lub response.session_info (dla postępów - tak było w API)
                // Ujednolicamy: zakładamy, że podstawowe info o sesji jest zawsze w response.data, jeśli to główny obiekt
                // lub w response.data.session_info, jeśli dane są zagnieżdżone.
                // Dla /api/admin/get_session_results.php, session_info jest przy progress, a resultsData zawiera dane sesji.
                const sessionInfoForDisplay = response.type === 'progress' ? response.session_info : response.data;

                if (sessionTitleEl && sessionInfoForDisplay && sessionInfoForDisplay.title) {
                    sessionTitleEl.textContent = `Postęp / Wyniki Sesji: ${htmlspecialchars(sessionInfoForDisplay.title)} (ID: ${htmlspecialchars(sessionInfoForDisplay.session_id)})`;
                } else if (sessionTitleEl) {
                     sessionTitleEl.textContent = `Postęp / Wyniki Sesji (ID: ${htmlspecialchars(currentResultsSessionId)})`;
                }


                if (sessionStatusInfoEl && sessionInfoForDisplay && sessionInfoForDisplay.status) {
                    let statusHtml = `<p>Status sesji: <span class="badge badge-${sessionInfoForDisplay.status === 'open' ? 'success' : 'secondary'}">${sessionInfoForDisplay.status === 'open' ? 'Otwarta' : 'Zamknięta'}</span>`;
                    if (sessionInfoForDisplay.code) {
                        statusHtml += ` | Kod: <strong>${htmlspecialchars(sessionInfoForDisplay.code)}</strong>`;
                    }
                    statusHtml += `</p>`;
                    sessionStatusInfoEl.innerHTML = statusHtml;
                }


                if (response.type === 'progress') {
                    renderSessionProgress(resultsArea, response.data); // response.data to tutaj dane postępu
                    if (closeThisSessionBtn && sessionInfoForDisplay && sessionInfoForDisplay.status === 'open') {
                        closeThisSessionBtn.classList.remove('d-none');
                    }
                } else if (response.type === 'results') {
                    renderSessionResults(resultsArea, response.data); 
                } else {
                    resultsArea.innerHTML = `<p class="alert alert-warning">Otrzymano nieoczekiwany typ danych: ${htmlspecialchars(response.type)}</p>`;
                }
            } else {
                const message = response.message || 'Nie udało się załadować danych sesji.';
                displayMessage(resultsPageMessageElId, message, false);
                resultsArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(message)}</p>`;
            }
        } catch (error) {
            console.error('loadResultsPageData: Error loading session data:', error);
            const errorMessage = error.message || 'Wystąpił krytyczny błąd podczas ładowania danych.';
            displayMessage(resultsPageMessageElId, errorMessage, false);
            resultsArea.innerHTML = `<p class="alert alert-danger">${htmlspecialchars(errorMessage)}</p>`;
        }
    }

    function renderSessionProgress(container, progressData) {
        console.log('renderSessionProgress: Rendering progress data:', progressData);
        // progressData to obiekt zwrócony przez VotingSession::getSessionProgress()
        // czyli np. { session_id, title, status, total_resolutions, total_joined_participants, ... }
        if (!progressData) {
            container.innerHTML = '<p class="alert alert-warning">Brak danych o postępach.</p>';
            return;
        }

        const completionPercentage = (progressData.expected_total_votes > 0) ? 
            ((progressData.total_votes_casted / progressData.expected_total_votes) * 100).toFixed(1) : 0;

        let html = `
            <div class="card mt-3">
                <div class="card-header bg-light">
                    <h4 class="mb-0">Postęp Głosowania</h4>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Całkowita liczba uchwał:
                        <span class="badge badge-primary badge-pill">${progressData.total_resolutions}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Liczba dołączonych uczestników:
                        <span class="badge badge-primary badge-pill">${progressData.total_joined_participants}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Uczestnicy, którzy zagłosowali przynajmniej raz:
                        <span class="badge badge-info badge-pill">${progressData.participants_voted_at_least_once}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Uczestnicy, którzy zagłosowali na wszystkie uchwały:
                        <span class="badge badge-success badge-pill">${progressData.participants_voted_on_all_resolutions}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Łączna liczba oddanych głosów:
                        <span class="badge badge-secondary badge-pill">${progressData.total_votes_casted}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Oczekiwana łączna liczba głosów:
                        <span class="badge badge-secondary badge-pill">${progressData.expected_total_votes}</span>
                    </li>
                </ul>
                ${progressData.expected_total_votes > 0 ? `
                <div class="card-body">
                    <h5 class="card-title text-center">Procent Ukończenia Głosowania</h5>
                    <div class="progress mt-2" style="height: 30px; font-size: 1rem;">
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
        // resultsData to obiekt zwrócony przez VotingSession::getResults()
        // czyli { session_id, title, code, status, created_at, resolutions_results, total_participants_in_session }
        console.log('renderSessionResults: Rendering results data:', resultsData);
        if (!resultsData || !resultsData.resolutions_results) {
            container.innerHTML = '<p class="alert alert-warning">Brak danych o wynikach.</p>';
            return;
        }

        let html = `
            <div class="card mt-3">
                <div class="card-header bg-light">
                    <h4 class="mb-0">Szczegółowe Wyniki Głosowania</h4>
                </div>
                <div class="card-body">
                    <p><strong>Całkowita liczba uczestników w sesji:</strong> ${resultsData.total_participants_in_session !== undefined ? resultsData.total_participants_in_session : 'B/D'}</p>
        `;

        if (resultsData.resolutions_results.length === 0) {
            html += '<p class="alert alert-info mt-2">Brak uchwał w tej sesji.</p>';
        } else {
            html += `
                <div class="table-responsive">
                <table class="table table-striped table-hover mt-3">
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
        const sessionId = event.target.dataset.sessionId;
        if (!sessionId) return;

        if (!confirm(`Czy na pewno chcesz zamknąć sesję o ID: ${sessionId} z tej strony? Tej operacji nie można cofnąć.`)) {
            return;
        }
        
        const resultsPageMessageElId = 'results-page-message';
        displayMessage(resultsPageMessageElId, `Zamykanie sesji ID: ${sessionId}...`, true);

        try {
            const result = await apiFetch('admin/close_session.php', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: parseInt(sessionId) })
            });

            if (result.status === 'success') {
                displayMessage(resultsPageMessageElId, result.message || `Sesja ID: ${sessionId} została zamknięta. Odświeżanie...`, true, 3000);
                setTimeout(loadResultsPageData, 1000); 
            } else {
                displayMessage(resultsPageMessageElId, result.message || 'Nie udało się zamknąć sesji.', false, 7000);
            }
        } catch (error) {
            displayMessage(resultsPageMessageElId, `Błąd podczas zamykania sesji: ${htmlspecialchars(error.message)}`, false, 7000);
        }
    }


    // --- Page Specific Initializations & Event Listeners ---

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    if (window.location.pathname.endsWith('logout.php')) {
        const userLS = loadFromLocalStorage('currentUser'); 
        if (userLS) { 
            handleLogout(true);
        } else { 
            const logoutPageMessageEl = document.getElementById('logout-page-message');
            if (logoutPageMessageEl) logoutPageMessageEl.textContent = 'Nie jesteś aktualnie zalogowany lub sesja wygasła. Przekierowywanie...';
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        }
    }

    const createSessionPageForm = document.getElementById('create-session-form-page');
    if (createSessionPageForm) {
        createSessionPageForm.addEventListener('submit', handleCreateSession);
    }

    if (document.getElementById('active-sessions-list') && document.getElementById('closed-sessions-list')) {
        loadAdminDashboardData();
    }

    const joinSessionForm = document.getElementById('join-session-form');
    if (joinSessionForm) {
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

    if (document.getElementById('resolutions-voting-area')) {
        console.log('scripts.js: On vote page, calling loadVotingPageData.');
        loadVotingPageData();
    }
    
    // Results Page Initialization
    const resultsDataArea = document.getElementById('session-data-area'); 
    if (resultsDataArea && window.location.pathname.includes('results.php')) { 
        console.log('scripts.js: On results page, calling loadResultsPageData.');
        loadResultsPageData();

        const refreshBtn = document.getElementById('refresh-results-btn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadResultsPageData);
        }
        const closeThisSessionBtn = document.getElementById('close-this-session-btn');
        if (closeThisSessionBtn) {
            closeThisSessionBtn.addEventListener('click', handleCloseThisSessionClick);
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
            containerElement.innerHTML = '<p class="alert alert-info mt-3">Brak zakończonych sesji głosowań w historii.</p>'; // Dodano mt-3
            return;
        }

        // Ulepszone formatowanie daty
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            try {
                return new Date(dateString).toLocaleDateString('pl-PL', {
                    year: 'numeric', month: 'long', day: 'numeric',
                    hour: '2-digit', minute: '2-digit' 
                });
            } catch (e) {
                console.warn("Error formatting date:", dateString, e);
                return dateString; // Zwróć oryginalny string, jeśli błąd
            }
        };

        let html = `
            <div class="table-responsive">
            <table class="table table-striped table-hover mt-2">
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
            // Zakładamy, że API (VotingSession->getPublicData) zwraca teraz 'closed_at'
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
                        <!-- Przycisk PDF można dodać później, jeśli będzie taka funkcjonalność -->
                        <!-- <button class="btn btn-sm btn-primary ml-1 action-generate-pdf" data-session-id="${session.session_id}" title="Pobierz raport PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button> -->
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

    // History Page Initialization
    if (document.getElementById('history-sessions-list') && window.location.pathname.includes('history.php')) {
        console.log('scripts.js: On history page, calling loadHistoryPageData.');
        loadHistoryPageData();
    }


    if (!window.location.pathname.endsWith('logout.php')) {
        checkLoginStatusAndUpdateNav();
    }


    // --- LocalStorage Helper Functions ---
    const LS_PREFIX = 'votumNauczycieli_'; 
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
        newResolutionDiv.classList.add('resolution-input-group', 'card', 'mb-3');
        newResolutionDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center p-2" style="background-color: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <h5 class="mb-0" style="font-size: 1rem;">Uchwała #${displayResolutionNumber}</h5>
                <button type="button" class="btn btn-danger btn-sm btn-remove-resolution" style="line-height: 1; padding: 0.25rem 0.5rem;">Usuń</button>
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
             allRemoveBtns.forEach(btn => btn.style.display = 'inline-block');
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