        (() => {
            if (window.userManagementDrawerHandlersInstalled) return;
            window.userManagementDrawerHandlersInstalled = true;

            let activeManageButton = null;
            let closeTimer = null;
            let drawerClosing = false;

            const showBanner = (message, isError = false) => {
                const banner = document.getElementById('user-management-banner');
                if (!banner) return;
                banner.textContent = message;
                banner.className = `rounded-md p-4 ${isError ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800'}`;
            };

            const bindManageDialog = (dialog, panel, overlay) => {
                if (dialog.dataset.handlersBound) return;
                dialog.dataset.handlersBound = 'true';

                dialog.addEventListener('cancel', (event) => {
                    event.preventDefault();
                    closeManagePanel();
                });
                panel.addEventListener('transitionend', (event) => {
                    if (event.propertyName === 'transform' && drawerClosing && dialog.open) {
                        window.clearTimeout(closeTimer);
                        dialog.close();
                    }
                });
                dialog.addEventListener('close', () => {
                    document.getElementById('user-manage-content')?.replaceChildren();
                    if (activeManageButton?.isConnected) activeManageButton.focus();
                    activeManageButton = null;
                });
            };

            const openManagePanel = (button) => {
                const template = document.getElementById(button.dataset.templateId);
                const manageDialog = document.getElementById('user-manage-dialog');
                const managePanel = document.getElementById('user-manage-panel');
                const manageOverlay = document.getElementById('user-manage-overlay');
                const manageContent = document.getElementById('user-manage-content');
                if (!template || !manageDialog || !managePanel || !manageOverlay || !manageContent) return;

                bindManageDialog(manageDialog, managePanel, manageOverlay);
                drawerClosing = false;
                window.clearTimeout(closeTimer);
                activeManageButton = button;
                manageContent.replaceChildren(template.content.cloneNode(true));
                managePanel.classList.add('translate-x-full');
                manageOverlay.classList.add('opacity-0');
                manageDialog.showModal();
                requestAnimationFrame(() => {
                    if (!manageDialog.open) return;
                    managePanel.classList.remove('translate-x-full');
                    manageOverlay.classList.remove('opacity-0');
                    document.getElementById('user-manage-close')?.focus();
                });
            };

            const closeManagePanel = () => {
                const manageDialog = document.getElementById('user-manage-dialog');
                const managePanel = document.getElementById('user-manage-panel');
                const manageOverlay = document.getElementById('user-manage-overlay');
                if (!manageDialog || !managePanel || !manageOverlay) return;
                if (!manageDialog.open) return;

                drawerClosing = true;
                managePanel.classList.add('translate-x-full');
                manageOverlay.classList.add('opacity-0');
                window.clearTimeout(closeTimer);
                closeTimer = window.setTimeout(() => {
                    if (manageDialog.open) manageDialog.close();
                }, 280);
            };

            document.addEventListener('click', (event) => {
                const manageButton = event.target.closest('.open-user-manage');
                if (manageButton) {
                    openManagePanel(manageButton);
                    return;
                }

                if (event.target.closest('#user-manage-close')) {
                    closeManagePanel();
                    return;
                }

                const manageDialog = document.getElementById('user-manage-dialog');
                if (event.target === manageDialog || event.target.id === 'user-manage-overlay') {
                    closeManagePanel();
                    return;
                }

                if (event.target.closest('#password-reset-copy')) {
                    const passwordValue = document.getElementById('password-reset-value');
                    navigator.clipboard.writeText(passwordValue.textContent)
                        .then(() => showBanner('Temporary password copied.'))
                        .catch(() => showBanner('Copy failed. Select and copy the password manually.', true));
                }

                if (event.target.closest('#password-reset-close')) {
                    document.getElementById('password-reset-dialog')?.close();
                }
            });

            document.addEventListener('close', (event) => {
                if (event.target.id === 'password-reset-dialog') {
                    document.getElementById('password-reset-value')?.replaceChildren();
                }
            }, true);

            document.addEventListener('submit', async (event) => {
                const form = event.target;

                if (form.matches('.user-role-division-form')) {
                    event.preventDefault();
                    const role = form.querySelector('[name="role"]');
                    const division = form.querySelector('[name="division"]');
                    const changes = [];

                    if (role && role.value !== role.dataset.originalValue) {
                        changes.push({ label: 'Role', url: form.dataset.roleUrl, field: 'role', value: role.value });
                    }
                    if (division && division.value !== division.dataset.originalValue) {
                        changes.push({ label: 'Division', url: form.dataset.divisionUrl, field: 'division', value: division.value });
                    }

                    if (changes.length === 0) {
                        showBanner('There are no changes to save.');
                        return;
                    }

                    const saveButton = form.querySelector('button[type="submit"]');
                    saveButton.disabled = true;
                    const saved = [];

                    try {
                        for (const change of changes) {
                            const payload = new FormData(form);
                            payload.set('_method', 'PATCH');
                            payload.set(change.field, change.value);
                            const response = await fetch(change.url, {
                                method: 'POST',
                                headers: { Accept: 'application/json' },
                                body: payload,
                                credentials: 'same-origin',
                            });

                            if (!response.ok) {
                                const result = await response.json().catch(() => ({}));
                                const message = result.message || `Unable to save ${change.label.toLowerCase()}.`;
                                throw new Error(saved.length ? `${saved.join(' and ')} saved, but ${message}` : message);
                            }

                            saved.push(change.label);
                            const row = document.querySelector(`[data-user-row="${form.dataset.userId}"]`);
                            if (change.field === 'role') {
                                role.dataset.originalValue = change.value;
                                row.querySelector('[data-user-role]').textContent = change.value;
                            } else {
                                division.dataset.originalValue = change.value;
                                row.querySelector('[data-user-division]').textContent = change.value;
                            }
                        }

                        showBanner(`${saved.join(' and ')} updated successfully.`);
                    } catch (error) {
                        showBanner(error.message, true);
                    } finally {
                        saveButton.disabled = false;
                    }
                }

                if (form.matches('.reset-password-form')) {
                    event.preventDefault();
                    const button = form.querySelector('button[type="submit"]');
                    const name = button.dataset.userName;

                    if (!window.confirm(`Reset password for ${name}?`)) return;

                    button.disabled = true;
                    document.getElementById('user-management-banner')?.classList.add('hidden');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: { Accept: 'application/json' },
                            body: new FormData(form),
                            credentials: 'same-origin',
                        });
                        const result = await response.json();

                        if (!response.ok) throw new Error(result.message || 'Unable to reset this password.');

                        const passwordValue = document.getElementById('password-reset-value');
                        const passwordDialog = document.getElementById('password-reset-dialog');
                        passwordValue.textContent = result.password;
                        passwordDialog.showModal();
                    } catch (error) {
                        showBanner(error.message, true);
                    } finally {
                        button.disabled = false;
                    }
                }

                if (form.matches('.danger-action-form')) {
                    const label = form.dataset.actionLabel;
                    const name = form.dataset.userName;
                    if (!window.confirm(`${label} for ${name}?`)) event.preventDefault();
                }
            });

        })();
