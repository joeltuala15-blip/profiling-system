<?php
if (!function_exists('app_toast_from_success_code')) {
    function app_toast_from_success_code($code) {
        $messages = [
            'login_success' => 'Login successful. Welcome back.',
            'logout_success' => 'You have been logged out successfully.',
            'user_updated' => 'User details updated successfully.',
            'household_added' => 'Household added successfully. You can now add residents.',
            'household_updated' => 'Household information updated successfully.',
            'residents_added' => 'Resident records added successfully.',
            'resident_updated' => 'Resident information updated successfully.',
            'resident_archived' => 'Resident updated and moved to archived records.',
        ];

        if (!isset($messages[$code])) {
            return null;
        }

        return [
            'type' => 'success',
            'title' => 'Success',
            'message' => $messages[$code],
        ];
    }
}

if (!function_exists('app_toast_from_error_code')) {
    function app_toast_from_error_code($code) {
        $messages = [
            'session_expired' => 'Household session expired. Please start a new household entry.',
            'household_not_found' => 'Household not found.',
            'member_not_found' => 'Member not found.',
            'user_not_found' => 'User not found.',
            'survey_date_required' => 'Survey date is required.',
            'survey_date_future' => 'Survey date cannot be in the future.',
            'household_number_exists' => 'This Household Number already exists.',
        ];

        if (!isset($messages[$code])) {
            return null;
        }

        return [
            'type' => 'error',
            'title' => 'Action Needed',
            'message' => $messages[$code],
        ];
    }
}

if (!function_exists('app_toast_from_message')) {
    function app_toast_from_message($message, $type = 'error', $title = '') {
        $message = trim((string)$message);
        if ($message === '') {
            return null;
        }

        $type = in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'error';
        if ($title === '') {
            $title = $type === 'success' ? 'Success' : 'Action Needed';
        }

        return [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }
}

if (!function_exists('render_app_toasts')) {
    function render_app_toasts($toasts) {
        $toasts = array_values(array_filter($toasts));
        ?>
        <style>
            .app-toast-stack {
                position: fixed;
                top: 24px;
                right: 28px;
                z-index: 100000;
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: min(380px, calc(100vw - 40px));
                pointer-events: none;
            }
            .app-toast {
                display: grid;
                grid-template-columns: 38px 1fr 24px;
                gap: 12px;
                align-items: start;
                padding: 14px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                background: #ffffff;
                box-shadow: 0 18px 38px rgba(15, 23, 42, 0.18);
                color: #1e293b;
                pointer-events: auto;
                animation: appToastIn 0.22s ease-out;
            }
            .app-toast-icon {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }
            .app-toast-success .app-toast-icon { background: #dcfce7; color: #166534; }
            .app-toast-error .app-toast-icon { background: #fee2e2; color: #991b1b; }
            .app-toast-warning .app-toast-icon { background: #fef3c7; color: #92400e; }
            .app-toast-info .app-toast-icon { background: #dbeafe; color: #1d4ed8; }
            .app-toast-title { font-weight: 800; font-size: 14px; margin-bottom: 3px; }
            .app-toast-message { color: #64748b; font-size: 13px; line-height: 1.45; white-space: pre-line; }
            .app-toast-close {
                width: 24px;
                height: 24px;
                border: none;
                background: transparent;
                color: #94a3b8;
                cursor: pointer;
                border-radius: 6px;
            }
            .app-toast-close:hover { background: #f1f5f9; color: #334155; }
            @keyframes appToastIn {
                from { opacity: 0; transform: translateX(18px); }
                to { opacity: 1; transform: translateX(0); }
            }
            @media (max-width: 768px) {
                .app-toast-stack { top: 16px; right: 20px; left: 20px; width: auto; }
            }
        </style>
        <div class="app-toast-stack" aria-live="polite">
            <?php foreach ($toasts as $toast): ?>
                <?php
                    $allowed_types = ['success', 'error', 'warning', 'info'];
                    $toast_type = $toast['type'] ?? 'success';
                    $type = in_array($toast_type, $allowed_types, true) ? $toast_type : 'success';
                    $icons = [
                        'success' => 'fa-check',
                        'error' => 'fa-triangle-exclamation',
                        'warning' => 'fa-circle-exclamation',
                        'info' => 'fa-circle-info',
                    ];
                    $icon = $icons[$type];
                ?>
                <div class="app-toast app-toast-<?php echo htmlspecialchars($type); ?>">
                    <div class="app-toast-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                    <div>
                        <div class="app-toast-title"><?php echo htmlspecialchars($toast['title'] ?? 'Notice'); ?></div>
                        <div class="app-toast-message"><?php echo htmlspecialchars($toast['message'] ?? ''); ?></div>
                    </div>
                    <button type="button" class="app-toast-close" aria-label="Close notification" onclick="this.closest('.app-toast').remove()">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function dismissAppToast(toast) {
                    if (!toast || toast.dataset.dismissing === '1') return;

                    toast.dataset.dismissing = '1';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(18px)';
                    toast.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                    setTimeout(function() {
                        toast.remove();
                    }, 220);
                }

                function queueToastDismiss(toast) {
                    setTimeout(function() {
                        dismissAppToast(toast);
                    }, 4500);
                }

                window.showAppToast = function(message, type, title) {
                    const options = typeof message === 'object' && message !== null ? message : {
                        message: message,
                        type: type,
                        title: title
                    };
                    const toastType = ['success', 'error', 'warning', 'info'].includes(options.type) ? options.type : 'error';
                    const icons = {
                        success: 'fa-check',
                        error: 'fa-triangle-exclamation',
                        warning: 'fa-circle-exclamation',
                        info: 'fa-circle-info'
                    };
                    const defaultTitles = {
                        success: 'Success',
                        error: 'Action Needed',
                        warning: 'Notice',
                        info: 'Notice'
                    };
                    let stack = document.querySelector('.app-toast-stack');

                    if (!stack) {
                        stack = document.createElement('div');
                        stack.className = 'app-toast-stack';
                        stack.setAttribute('aria-live', 'polite');
                        document.body.appendChild(stack);
                    }

                    const toast = document.createElement('div');
                    toast.className = 'app-toast app-toast-' + toastType;
                    toast.innerHTML = `
                        <div class="app-toast-icon"><i class="fa-solid ${icons[toastType]}"></i></div>
                        <div>
                            <div class="app-toast-title"></div>
                            <div class="app-toast-message"></div>
                        </div>
                        <button type="button" class="app-toast-close" aria-label="Close notification">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    `;

                    toast.querySelector('.app-toast-title').textContent = options.title || defaultTitles[toastType];
                    toast.querySelector('.app-toast-message').textContent = options.message || '';
                    toast.querySelector('.app-toast-close').addEventListener('click', function() {
                        dismissAppToast(toast);
                    });

                    stack.appendChild(toast);
                    queueToastDismiss(toast);
                    return toast;
                };

                window.appNotify = window.showAppToast;

                document.querySelectorAll('.app-toast').forEach(queueToastDismiss);

                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    if (url.searchParams.has('success') || url.searchParams.has('error')) {
                        url.searchParams.delete('success');
                        url.searchParams.delete('error');
                        window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
                    }
                }
            });
        </script>
        <?php
    }
}

if (!function_exists('render_form_draft_assets')) {
    function render_form_draft_assets() {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <script>
            (function() {
                if (window.AppFormDraft) return;

                const keyPrefix = 'residents.formDraft.';
                const ignoredTypes = new Set(['button', 'submit', 'reset', 'file', 'password']);

                function storageFor(type) {
                    try {
                        return type === 'local' ? window.localStorage : window.sessionStorage;
                    } catch (error) {
                        return null;
                    }
                }

                function storageKey(key) {
                    return keyPrefix + key;
                }

                function shouldSkip(field, options) {
                    if (!field || !field.name || field.disabled) return true;
                    const type = (field.type || '').toLowerCase();
                    if (ignoredTypes.has(type)) return true;
                    if ((options.skipNames || []).includes(field.name)) return true;
                    return (options.skipNamePatterns || []).some((pattern) => {
                        try {
                            return new RegExp(pattern).test(field.name);
                        } catch (error) {
                            return false;
                        }
                    });
                }

                function groupedFields(form, options) {
                    const groups = new Map();
                    Array.from(form.elements || []).forEach((field) => {
                        if (shouldSkip(field, options)) return;
                        if (!groups.has(field.name)) groups.set(field.name, []);
                        groups.get(field.name).push(field);
                    });
                    return groups;
                }

                function collect(form, options) {
                    const fields = {};
                    groupedFields(form, options).forEach((group, name) => {
                        const first = group[0];
                        const type = (first.type || '').toLowerCase();

                        if (type === 'radio') {
                            const checked = group.find((field) => field.checked);
                            fields[name] = checked ? checked.value : null;
                            return;
                        }

                        if (type === 'checkbox') {
                            if (group.length === 1) {
                                fields[name] = group[0].checked ? (group[0].value || true) : false;
                            } else {
                                fields[name] = group.filter((field) => field.checked).map((field) => field.value);
                            }
                            return;
                        }

                        if (first.tagName === 'SELECT' && first.multiple) {
                            fields[name] = Array.from(first.selectedOptions).map((option) => option.value);
                            return;
                        }

                        fields[name] = first.value;
                    });

                    return {
                        savedAt: Date.now(),
                        fields: fields
                    };
                }

                function triggerField(field) {
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function restore(form, draft, options) {
                    if (!draft || !draft.fields) return;
                    const groups = groupedFields(form, options);

                    Object.keys(draft.fields).forEach((name) => {
                        const group = groups.get(name);
                        if (!group || group.length === 0) return;
                        const value = draft.fields[name];
                        const first = group[0];
                        const type = (first.type || '').toLowerCase();

                        if (type === 'radio') {
                            group.forEach((field) => {
                                const next = value !== null && field.value === value;
                                if (field.checked !== next) {
                                    field.checked = next;
                                    triggerField(field);
                                }
                            });
                            return;
                        }

                        if (type === 'checkbox') {
                            group.forEach((field) => {
                                let next = false;
                                if (Array.isArray(value)) {
                                    next = value.includes(field.value);
                                } else if (value === true) {
                                    next = true;
                                } else if (value !== false && value !== null) {
                                    next = field.value === value;
                                }

                                if (field.checked !== next) {
                                    field.checked = next;
                                    triggerField(field);
                                }
                            });
                            return;
                        }

                        if (first.tagName === 'SELECT' && first.multiple && Array.isArray(value)) {
                            Array.from(first.options).forEach((option) => {
                                option.selected = value.includes(option.value);
                            });
                            triggerField(first);
                            return;
                        }

                        if (first.value !== String(value ?? '')) {
                            first.value = value ?? '';
                            triggerField(first);
                        }
                    });
                }

                function loadDraft(options) {
                    const storage = storageFor(options.storage);
                    if (!storage) return null;

                    try {
                        const raw = storage.getItem(storageKey(options.key));
                        return raw ? JSON.parse(raw) : null;
                    } catch (error) {
                        return null;
                    }
                }

                function saveDraft(form, options) {
                    const storage = storageFor(options.storage);
                    if (!storage) return;

                    try {
                        storage.setItem(storageKey(options.key), JSON.stringify(collect(form, options)));
                    } catch (error) {
                    }
                }

                function clearDraft(key, storageType) {
                    const storage = storageFor(storageType || 'session');
                    if (!storage || !key) return;

                    try {
                        storage.removeItem(storageKey(key));
                    } catch (error) {}
                }

                function bind(form, config) {
                    if (!form || !config || !config.key || form.dataset.formDraftBound === config.key) return;

                    const options = Object.assign({
                        storage: 'session',
                        skipNames: [],
                        skipNamePatterns: []
                    }, config);
                    form.dataset.formDraftBound = options.key;

                    if (options.clearOnLoad) {
                        clearDraft(options.key, options.storage);
                    } else {
                        const draft = loadDraft(options);
                        if (draft && typeof options.beforeRestore === 'function') {
                            options.beforeRestore(form, draft);
                        }
                        restore(form, draft, options);
                        if (draft && typeof options.afterRestore === 'function') {
                            options.afterRestore(form, draft);
                        }
                    }

                    let timer = null;
                    const queueSave = () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => saveDraft(form, options), 140);
                    };

                    form.addEventListener('input', queueSave);
                    form.addEventListener('change', queueSave);
                    window.addEventListener('beforeunload', () => saveDraft(form, options));
                    queueSave();
                }

                window.AppFormDraft = {
                    bind: bind,
                    clear: clearDraft,
                    save: saveDraft,
                    load: loadDraft
                };
            })();
        </script>
        <?php
    }
}

if (!function_exists('render_form_draft_script')) {
    function render_form_draft_script($form_selector, $storage_key, $options = []) {
        render_form_draft_assets();

        $config = [
            'key' => $storage_key,
            'storage' => $options['storage'] ?? 'session',
            'skipNames' => $options['skip_names'] ?? [],
            'skipNamePatterns' => $options['skip_name_patterns'] ?? [],
            'clearOnLoad' => !empty($options['clear_on_load']),
        ];
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $selector_json = json_encode($form_selector, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.querySelector(<?php echo $selector_json; ?>);
                if (form && window.AppFormDraft) {
                    window.AppFormDraft.bind(form, <?php echo $json; ?>);
                }
            });
        </script>
        <?php
    }
}
