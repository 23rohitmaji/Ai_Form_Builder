import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    BarChart3,
    Bot,
    CheckCircle2,
    Copy,
    Download,
    Eye,
    FileText,
    GripVertical,
    Loader2,
    LogOut,
    Plus,
    RefreshCw,
    Save,
    Send,
    Trash2,
} from 'lucide-react';
import { apiRequest, formatErrors } from './apiClient';
import { API_PREFIX, DEFAULT_PROMPTS, FIELD_PALETTE, FIELD_TYPES, OPTION_TYPES, STORAGE_KEYS } from './constants';

const emptyField = (type = 'text') => ({
    key: `field_${Date.now()}`,
    label: FIELD_PALETTE.find(([value]) => value === type)?.[1] || 'New field',
    type,
    placeholder: '',
    help_text: '',
    default_value: '',
    is_required: false,
    options: OPTION_TYPES.includes(type) ? ['Option 1', 'Option 2'] : [],
    validation_rules: [],
    section: '',
    step: '',
});

const starterForm = () => ({
    title: 'Workshop Registration',
    slug: `workshop-registration-${Date.now()}`,
    description: 'Collect registrations for an AI workshop.',
    is_published: true,
    fields: [
        { key: 'full_name', label: 'Full name', type: 'text', is_required: true },
        { key: 'email', label: 'Email', type: 'email', is_required: true },
        { key: 'attendance_mode', label: 'Attendance mode', type: 'dropdown', is_required: true, options: ['Online', 'In person'] },
    ],
});

function App() {
    const publicSlug = window.location.pathname.match(/^\/f\/([^/]+)/)?.[1];

    if (publicSlug) {
        return <PublicForm slug={publicSlug} />;
    }

    return <BuilderApp />;
}

function BuilderApp() {
    const [token, setToken] = useState(localStorage.getItem(STORAGE_KEYS.sessionToken) || localStorage.getItem(STORAGE_KEYS.legacyToken) || '');
    const [user, setUser] = useState(JSON.parse(localStorage.getItem(STORAGE_KEYS.userDetails) || localStorage.getItem(STORAGE_KEYS.legacyUser) || 'null'));
    const [forms, setForms] = useState([]);
    const [activeForm, setActiveForm] = useState(null);
    const [editor, setEditor] = useState(starterForm());
    const [submissions, setSubmissions] = useState([]);
    const [submissionMeta, setSubmissionMeta] = useState(null);
    const [submissionSearch, setSubmissionSearch] = useState('');
    const [submissionPage, setSubmissionPage] = useState(1);
    const [analytics, setAnalytics] = useState(null);
    const [prompt, setPrompt] = useState(DEFAULT_PROMPTS.create);
    const [editPrompt, setEditPrompt] = useState(DEFAULT_PROMPTS.edit);
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState('Ready');
    const [errors, setErrors] = useState([]);
    const [authMode, setAuthMode] = useState('register');
    const [authForms, setAuthForms] = useState({
        register: { name: '', email: '', password: '' },
        login: { email: '', password: '' },
    });

    const authed = Boolean(token);

    useEffect(() => {
        const legacyToken = localStorage.getItem(STORAGE_KEYS.legacyToken);
        const legacyUser = localStorage.getItem(STORAGE_KEYS.legacyUser);

        if (legacyToken && !localStorage.getItem(STORAGE_KEYS.sessionToken)) {
            localStorage.setItem(STORAGE_KEYS.sessionToken, legacyToken);
        }

        if (legacyUser && !localStorage.getItem(STORAGE_KEYS.userDetails)) {
            localStorage.setItem(STORAGE_KEYS.userDetails, legacyUser);
        }

        localStorage.removeItem(STORAGE_KEYS.legacyToken);
        localStorage.removeItem(STORAGE_KEYS.legacyUser);
    }, []);

    useEffect(() => {
        if (authed) {
            loadForms();
        }
    }, [authed]);

    useEffect(() => {
        if (!activeForm) return;

        const refresh = () => {
            loadForms({ silent: true });
            loadAnalytics(activeForm.id, { silent: true });
            loadSubmissions(activeForm.id, submissionPage, submissionSearch, { silent: true });
        };
        const onStorage = (event) => {
            if (event.key === 'form_builder_submission_event') {
                refresh();
            }
        };
        const timer = window.setInterval(refresh, 10000);
        window.addEventListener('storage', onStorage);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('storage', onStorage);
        };
    }, [activeForm?.id, submissionPage, submissionSearch]);

    async function request(path, options = {}) {
        const response = await apiRequest(path, { ...options, token });

        return response.data;
    }

    async function run(message, callback, options = {}) {
        if (!options.silent) {
            setBusy(true);
            setNotice(message);
            setErrors([]);
        }
        try {
            const result = await callback();
            if (!options.silent) {
                setNotice('Done');
            }
            return result;
        } catch (error) {
            if (!options.silent) {
                setNotice(error.message || 'Request failed.');
                setErrors(formatErrors(error));
            }
        } finally {
            if (!options.silent) {
                setBusy(false);
            }
        }
    }

    async function submitAuth(event) {
        event.preventDefault();
        const payload = authForms[authMode];
        const localErrors = validateAuthPayload(authMode, payload);

        if (localErrors.length) {
            setNotice('Please fix the highlighted fields.');
            setErrors(localErrors);

            return;
        }

        await run('Authenticating...', async () => {
            const data = await request(`${API_PREFIX}/${authMode}`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            setSession(data.token, data.user);
        });
    }

    async function logout() {
        await run('Logging out...', async () => {
            await request(`${API_PREFIX}/logout`, { method: 'POST' });
            clearSession();
            setForms([]);
            setActiveForm(null);
        });
    }

    async function loadForms(options = {}) {
        await run('Loading forms...', async () => {
            const data = await request(`${API_PREFIX}/forms`);
            setForms(data.data || []);
        }, options);
    }

    async function saveForm() {
        await run('Saving form...', async () => {
            const payload = normalizeEditor(editor);
            const data = activeForm
                ? await request(`${API_PREFIX}/forms/${activeForm.id}`, { method: 'PUT', body: JSON.stringify(payload) })
                : await request(`${API_PREFIX}/forms`, { method: 'POST', body: JSON.stringify(payload) });
            setActiveForm(data.data);
            setEditor(resourceToEditor(data.data));
            await loadForms();
            await loadAnalytics(data.data.id);
        });
    }

    async function deleteForm(form) {
        if (!confirm(`Delete "${form.title}"?`)) return;
        await run('Deleting form...', async () => {
            await request(`${API_PREFIX}/forms/${form.id}`, { method: 'DELETE' });
            if (activeForm?.id === form.id) {
                newForm();
            }
            await loadForms();
        });
    }

    async function openForm(form) {
        await run('Opening form...', async () => {
            const data = await request(`${API_PREFIX}/forms/${form.id}`);
            setActiveForm(data.data);
            setEditor(resourceToEditor(data.data));
            setSubmissionPage(1);
            setSubmissionSearch('');
            await loadSubmissions(form.id, 1, '');
            await loadAnalytics(form.id);
        });
    }

    async function loadSubmissions(formId = activeForm?.id, page = submissionPage, search = submissionSearch, options = {}) {
        if (!formId) return;
        await run('Loading submissions...', async () => {
            const params = new URLSearchParams({ page, per_page: 5 });
            if (search) params.set('search', search);
            const data = await request(`${API_PREFIX}/forms/${formId}/submissions?${params.toString()}`);
            setSubmissions(data.data || []);
            setSubmissionMeta(data.meta || null);
        }, options);
    }

    async function loadAnalytics(formId = activeForm?.id, options = {}) {
        if (!formId) return;
        await run('Loading analytics...', async () => {
            const data = await request(`${API_PREFIX}/forms/${formId}/analytics`);
            setAnalytics(data);
        }, options);
    }

    async function generateAiSchema() {
        await run('Generating schema...', async () => {
            const data = await request(`${API_PREFIX}/ai/forms`, {
                method: 'POST',
                body: JSON.stringify({ prompt }),
            });
            setActiveForm(null);
            setEditor(resourceToEditor({ ...data.schema, slug: `${slugify(data.schema.title)}-${Date.now()}`, fields: sanitizeFields(data.schema.fields || []) }));
        });
    }

    async function editAiSchema() {
        await run('Editing current schema...', async () => {
            const data = await request(`${API_PREFIX}/ai/forms`, {
                method: 'POST',
                body: JSON.stringify({
                    prompt: editPrompt,
                    schema: normalizeEditor(editor),
                }),
            });

            setEditor(resourceToEditor({
                ...data.schema,
                slug: editor.slug || `${slugify(data.schema.title)}-${Date.now()}`,
                is_published: editor.is_published,
            }));
        });
    }

    async function exportCsv() {
        if (!activeForm) return;

        await run('Exporting CSV...', async () => {
            const response = await apiRequest(`${API_PREFIX}/forms/${activeForm.id}/submissions/export`, {
                responseType: 'blob',
                token,
            });
            const blob = response.data;
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${activeForm.slug}-submissions.csv`;
            link.click();
            URL.revokeObjectURL(url);
        });
    }

    function newForm() {
        setActiveForm(null);
        setEditor(starterForm());
        setSubmissions([]);
        setAnalytics(null);
    }

    function setSession(nextToken, nextUser) {
        localStorage.setItem(STORAGE_KEYS.sessionToken, nextToken);
        localStorage.setItem(STORAGE_KEYS.userDetails, JSON.stringify(nextUser));
        localStorage.removeItem(STORAGE_KEYS.legacyToken);
        localStorage.removeItem(STORAGE_KEYS.legacyUser);
        setToken(nextToken);
        setUser(nextUser);
    }

    function clearSession() {
        localStorage.removeItem(STORAGE_KEYS.sessionToken);
        localStorage.removeItem(STORAGE_KEYS.userDetails);
        localStorage.removeItem(STORAGE_KEYS.legacyToken);
        localStorage.removeItem(STORAGE_KEYS.legacyUser);
        setToken('');
        setUser(null);
    }

    function updateAuthField(field, value) {
        setAuthForms({
            ...authForms,
            [authMode]: {
                ...authForms[authMode],
                [field]: value,
            },
        });
    }

    function addField(type) {
        setEditor({ ...editor, fields: [...(editor.fields || []), emptyField(type)] });
    }

    function duplicateField(index) {
        const copy = {
            ...editor.fields[index],
            key: `${editor.fields[index].key}_copy_${Date.now()}`,
            label: `${editor.fields[index].label} Copy`,
        };

        setEditor({
            ...editor,
            fields: [...editor.fields.slice(0, index + 1), copy, ...editor.fields.slice(index + 1)],
        });
    }

    function moveField(from, to) {
        if (to < 0 || to >= editor.fields.length) return;
        const fields = [...editor.fields];
        const [field] = fields.splice(from, 1);
        fields.splice(to, 0, field);
        setEditor({ ...editor, fields });
    }

    if (!authed) {
        return (
            <main className="auth-shell">
                <section className="auth-panel">
                    <div>
                        <span className="eyebrow">Backend Developer Laravel</span>
                        <h1>AI Form Builder</h1>
                        <p>Build forms, publish them, collect submissions, inspect analytics, export CSVs, and generate schemas with AI.</p>
                    </div>
                    <form onSubmit={submitAuth} className="auth-form">
                        <div className="segmented">
                            <button type="button" className={authMode === 'register' ? 'active' : ''} onClick={() => setAuthMode('register')}>Register</button>
                            <button type="button" className={authMode === 'login' ? 'active' : ''} onClick={() => setAuthMode('login')}>Login</button>
                        </div>
                        {authMode === 'register' && <TextInput label="Name" value={authForms.register.name} placeholder="Your name" required onChange={(name) => updateAuthField('name', name)} />}
                        <TextInput label="Email" type="email" value={authForms[authMode].email} placeholder="you@example.com" required autoComplete="email" onChange={(email) => updateAuthField('email', email)} />
                        <TextInput label="Password" type="password" value={authForms[authMode].password} placeholder="Minimum 8 characters" required minLength={8} autoComplete={authMode === 'register' ? 'new-password' : 'current-password'} onChange={(password) => updateAuthField('password', password)} />
                        <button className="primary" disabled={busy}>{busy ? <Loader2 className="spin" /> : <CheckCircle2 />} Continue</button>
                        <p className="notice">{notice}</p>
                        {errors.length > 0 && <ErrorList errors={errors} />}
                    </form>
                    <StatusOverlay busy={busy} notice={notice} errors={errors} />
                </section>
            </main>
        );
    }

    return (
        <main className="app-shell">
            <header className="app-header">
                <div>
                    <span className="eyebrow">AI Form Builder</span>
                    <h1>Form Operations Console</h1>
                </div>
                <div className="header-actions">
                    <span>{user?.email}</span>
                    <button className="icon-button" title="Refresh" onClick={loadForms}><RefreshCw /></button>
                    <button className="icon-button" title="Logout" onClick={logout}><LogOut /></button>
                </div>
            </header>

            <section className="workspace">
                <aside className="sidebar">
                    <div className="sidebar-title">
                        <h2>Forms</h2>
                        <button className="icon-button" title="New form" onClick={newForm}><Plus /></button>
                    </div>
                    <div className="form-list">
                        {forms.map((form) => (
                            <button key={form.id} className={`form-row ${activeForm?.id === form.id ? 'selected' : ''}`} onClick={() => openForm(form)}>
                                <span>{form.title}</span>
                                <small>{form.submissions_count ?? 0} submissions</small>
                            </button>
                        ))}
                        {!forms.length && <p className="empty">No forms yet. Create one from the editor.</p>}
                    </div>
                </aside>

                <section className="main-grid">
                    <section className="panel editor">
                        <div className="panel-heading">
                            <div>
                                <h2>{activeForm ? 'Edit Form' : 'Create Form'}</h2>
                                <p>Configure fields, publish status, and public slug.</p>
                            </div>
                            <div className="button-row">
                                {activeForm && editor.is_published ? (
                                    <a className="secondary" href={`/f/${editor.slug}`} target="_blank"><Eye /> Open public form</a>
                                ) : (
                                    <button className="secondary" disabled title="Save a published form first"><Eye /> Open public form</button>
                                )}
                                <button className="primary" onClick={saveForm} disabled={busy}><Save /> Save</button>
                            </div>
                        </div>
                        {errors.length > 0 && <ErrorList errors={errors} />}

                        <div className="two-col">
                            <TextInput label="Title" value={editor.title || ''} onChange={(title) => setEditor({ ...editor, title })} />
                            <TextInput label="Slug" value={editor.slug || ''} onChange={(slug) => setEditor({ ...editor, slug })} />
                        </div>
                        <label className="input-group">
                            Description
                            <textarea value={editor.description || ''} onChange={(event) => setEditor({ ...editor, description: event.target.value })} />
                        </label>
                        <label className="checkline">
                            <input type="checkbox" checked={Boolean(editor.is_published)} onChange={(event) => setEditor({ ...editor, is_published: event.target.checked })} />
                            Published
                        </label>
                        <label className="checkline">
                            <input type="checkbox" checked={editor.store_submissions !== false} onChange={(event) => setEditor({ ...editor, store_submissions: event.target.checked })} />
                            Store submissions
                        </label>

                        <div className="field-header">
                            <div>
                                <h3>Fields</h3>
                                <p className="muted small">Click a field type or drag it into the list.</p>
                            </div>
                            <button className="secondary compact" onClick={() => addField('text')}><Plus /> Add text</button>
                        </div>
                        <div className="field-palette">
                            {FIELD_PALETTE.map(([type, label]) => (
                                <button
                                    key={type}
                                    className="palette-button"
                                    draggable
                                    onClick={() => addField(type)}
                                    onDragStart={(event) => event.dataTransfer.setData('field-type', type)}
                                >
                                    <Plus /> {label}
                                </button>
                            ))}
                        </div>
                        <div
                            className="fields"
                            onDragOver={(event) => event.preventDefault()}
                            onDrop={(event) => {
                                const type = event.dataTransfer.getData('field-type');
                                if (type) addField(type);
                            }}
                        >
                            {(editor.fields || []).map((field, index) => (
                                <FieldEditor
                                    key={`${field.key}-${index}`}
                                    field={field}
                                    index={index}
                                    onChange={(next) => updateField(editor, setEditor, index, next)}
                                    onMove={(to) => moveField(index, to)}
                                    onMoveFrom={(from) => moveField(from, index)}
                                    onDuplicate={() => duplicateField(index)}
                                    onDelete={() => setEditor({ ...editor, fields: editor.fields.filter((_, i) => i !== index) })}
                                />
                            ))}
                        </div>
                    </section>

                    <aside className="right-rail">
                        <section className="panel">
                            <div className="panel-heading slim">
                                <h2>AI Create</h2>
                                <Bot />
                            </div>
                            <textarea className="prompt" value={prompt} onChange={(event) => setPrompt(event.target.value)} />
                            <button className="primary full" onClick={generateAiSchema} disabled={busy}><Bot /> Generate editable schema</button>
                        </section>

                        <section className="panel">
                            <div className="panel-heading slim">
                                <h2>AI Edit</h2>
                                <Bot />
                            </div>
                            <textarea className="prompt" value={editPrompt} onChange={(event) => setEditPrompt(event.target.value)} />
                            <div className="example-chips">
                                {['Add an emergency contact section.', 'Make phone required.', 'Translate labels to Hindi.'].map((example) => (
                                    <button key={example} className="chip" onClick={() => setEditPrompt(example)}>{example}</button>
                                ))}
                            </div>
                            <button className="secondary full" onClick={editAiSchema} disabled={busy || !(editor.fields || []).length}><Bot /> Apply to current form</button>
                        </section>

                        <section className="panel">
                            <div className="panel-heading slim">
                                <h2>Analytics</h2>
                                <BarChart3 />
                            </div>
                            <div className="metrics">
                                <Metric label="Total" value={analytics?.total_submissions ?? 0} />
                                <Metric label="Processed" value={analytics?.processed_submissions ?? 0} />
                            </div>
                            <button className="secondary full" onClick={() => loadAnalytics()} disabled={!activeForm}><RefreshCw /> Refresh analytics</button>
                        </section>

                        <section className="panel">
                            <div className="panel-heading slim">
                                <h2>Submissions</h2>
                                <FileText />
                            </div>
                            <div className="submission-tools">
                                <input
                                    placeholder="Search submissions"
                                    value={submissionSearch}
                                    onChange={(event) => setSubmissionSearch(event.target.value)}
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            setSubmissionPage(1);
                                            loadSubmissions(activeForm?.id, 1, event.currentTarget.value);
                                        }
                                    }}
                                />
                                <button className="secondary" onClick={() => {
                                    setSubmissionPage(1);
                                    loadSubmissions(activeForm?.id, 1, submissionSearch);
                                }} disabled={!activeForm}><RefreshCw /> Search</button>
                            </div>
                            <div className="submission-list">
                                {submissions.map((submission) => (
                                    <details key={submission.id}>
                                        <summary>Submission #{submission.id}</summary>
                                        <div className="answer-grid">
                                            {Object.entries(submission.answers || {}).map(([key, value]) => (
                                                <div className="answer-row" key={key}>
                                                    <span className="answer-key">{key}</span>
                                                    <span className="answer-value" title={formatAnswer(value)}>{formatAnswer(value)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </details>
                                ))}
                                {!submissions.length && <p className="empty">No submissions loaded.</p>}
                            </div>
                            <div className="button-row">
                                <button className="secondary" onClick={() => loadSubmissions(activeForm?.id, submissionPage, submissionSearch)} disabled={!activeForm}><RefreshCw /> Refresh</button>
                                <button className="secondary" onClick={exportCsv} disabled={!activeForm}><Download /> Export</button>
                            </div>
                            <div className="pager">
                                <button className="secondary compact" disabled={!submissionMeta || submissionPage <= 1} onClick={() => {
                                    const page = submissionPage - 1;
                                    setSubmissionPage(page);
                                    loadSubmissions(activeForm?.id, page, submissionSearch);
                                }}>Previous</button>
                                <span>Page {submissionMeta?.current_page || 1} of {submissionMeta?.last_page || 1}</span>
                                <button className="secondary compact" disabled={!submissionMeta || submissionPage >= submissionMeta.last_page} onClick={() => {
                                    const page = submissionPage + 1;
                                    setSubmissionPage(page);
                                    loadSubmissions(activeForm?.id, page, submissionSearch);
                                }}>Next</button>
                            </div>
                        </section>
                    </aside>
                </section>
            </section>

            <StatusOverlay busy={busy} notice={notice} errors={errors} />
            <footer className="toast">{busy && <Loader2 className="spin" />} {notice}</footer>
        </main>
    );
}

function FieldEditor({ field, index, onChange, onMove, onMoveFrom, onDuplicate, onDelete }) {
    const usesOptions = OPTION_TYPES.includes(field.type);
    const isDisplayOnly = field.type === 'section';
    const supportsDefault = !['file', 'section'].includes(field.type);

    return (
        <div
            className="field-card"
            draggable
            onDragStart={(event) => event.dataTransfer.setData('field-index', String(index))}
            onDragOver={(event) => event.preventDefault()}
            onDrop={(event) => {
                const raw = event.dataTransfer.getData('field-index');
                const from = Number(raw);
                if (raw !== '' && Number.isInteger(from)) onMoveFrom(from);
            }}
        >
            <div className="field-card-head">
                <strong><GripVertical /> Field {index + 1}</strong>
                <div className="field-actions">
                    <button className="icon-button" title="Move up" onClick={() => onMove(index - 1)}>↑</button>
                    <button className="icon-button" title="Move down" onClick={() => onMove(index + 1)}>↓</button>
                    <button className="icon-button" title="Duplicate field" onClick={onDuplicate}><Copy /></button>
                    <button className="icon-button danger" title="Delete field" onClick={onDelete}><Trash2 /></button>
                </div>
            </div>
            <div className="two-col">
                <TextInput label="Key" value={field.key || ''} onChange={(key) => onChange({ ...field, key })} />
                <TextInput label="Label" value={field.label || ''} onChange={(label) => onChange({ ...field, label })} />
            </div>
            <div className="two-col">
                <label className="input-group">
                    Type
                    <select value={field.type} onChange={(event) => onChange({ ...field, type: event.target.value })}>
                        {FIELD_TYPES.map((type) => <option key={type} value={type}>{type}</option>)}
                    </select>
                </label>
                <label className="checkline field-check">
                    <input type="checkbox" checked={Boolean(field.is_required)} onChange={(event) => onChange({ ...field, is_required: event.target.checked })} />
                    Required
                </label>
            </div>
            {!isDisplayOnly && (
                <div className="two-col">
                    <TextInput label="Placeholder" value={field.placeholder || ''} onChange={(placeholder) => onChange({ ...field, placeholder })} />
                    {supportsDefault ? (
                        <TextInput label="Default value" value={unwrapDefault(field.default_value)} onChange={(default_value) => onChange({ ...field, default_value })} />
                    ) : (
                        <label className="input-group muted-config">
                            Default value
                            <input value="Not used for file uploads" disabled />
                        </label>
                    )}
                </div>
            )}
            <label className="input-group">
                Help text
                <textarea value={field.help_text || ''} onChange={(event) => onChange({ ...field, help_text: event.target.value })} />
                <span className="help-text">Shown below the field on the public form.</span>
            </label>
            <div className="two-col">
                <TextInput label="Section group" value={field.section || ''} onChange={(section) => onChange({ ...field, section })} />
                <TextInput label="Step group" value={field.step || ''} onChange={(step) => onChange({ ...field, step })} />
            </div>
            <p className="help-text">Section and Step are grouping labels for organizing long forms. They are saved and returned by the API.</p>
            {usesOptions && (
                <label className="input-group">
                    Options, one per line
                    <textarea
                        className="options-box"
                        value={(field.options || []).join('\n')}
                        onChange={(event) => onChange({ ...field, options: splitLines(event.target.value) })}
                        placeholder={'Early bird, student\nGeneral admission\nVIP, sponsor'}
                    />
                </label>
            )}
            {!isDisplayOnly && (
                <label className="input-group">
                    Validation rules, one per line
                    <textarea
                        className="options-box"
                        value={(field.validation_rules || []).join('\n')}
                        onChange={(event) => onChange({ ...field, validation_rules: splitLines(event.target.value) })}
                        placeholder={'min:1\nmax:100\nmin_length:3\nmax_length:80\nurl\nregex:/^[A-Z]+$/\nfile_types:pdf,jpg\nfile_max:2048'}
                    />
                    <span className="help-text">Examples: min:1, max:5, min_length:3, max_length:80, url, regex:/^[A-Z]+$/, file_types:pdf,jpg, file_max:2048 KB.</span>
                </label>
            )}
        </div>
    );
}

function PublicForm({ slug }) {
    const [form, setForm] = useState(null);
    const [answers, setAnswers] = useState({});
    const [status, setStatus] = useState('Loading form...');
    const [submitted, setSubmitted] = useState(null);
    const [errors, setErrors] = useState([]);
    const [busy, setBusy] = useState(false);
    const inactive = !busy && !form && status === 'Form not active';

    useEffect(() => {
        setBusy(true);
        apiRequest(`${API_PREFIX}/public/forms/${slug}`)
            .then((response) => {
                setForm(response.data.data);
                setErrors([]);
                setStatus('Ready');
            })
            .catch((error) => {
                setStatus('Form not active');
                setErrors(formatErrors(error));
            })
            .finally(() => {
                setBusy(false);
            });
    }, [slug]);

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        setStatus('Submitting...');
        setSubmitted(null);
        setErrors([]);

        try {
            const response = await apiRequest(`${API_PREFIX}/public/forms/${slug}/submissions`, {
                method: 'POST',
                body: JSON.stringify({ answers }),
            });
            setStatus('Submission received.');
            setSubmitted(response.data.data);
            setAnswers({});
            localStorage.setItem('form_builder_submission_event', JSON.stringify({ slug, at: Date.now() }));
        } catch (error) {
            setStatus(error.message || 'Unable to submit form.');
            setErrors(formatErrors(error));
        } finally {
            setBusy(false);
        }
    }

    return (
        <main className="public-shell">
            <form className={`public-form ${inactive ? 'inactive-form' : ''}`} onSubmit={submit}>
                <div>
                    <span className="eyebrow">Published Form</span>
                    <h1>{inactive ? 'Form not active' : form?.title || 'Loading...'}</h1>
                    <p>{form?.description}</p>
                </div>
                {!inactive && form?.fields?.map((field) => (
                    <DynamicInput key={field.key} field={field} value={answers[field.key]} onChange={(value) => setAnswers({ ...answers, [field.key]: value })} />
                ))}
                {!inactive && <button className="primary" disabled={!form || busy}><Send /> Submit</button>}
                <p className="notice">{status}</p>
                {errors.length > 0 && !inactive && <ErrorList errors={errors} />}
                {submitted && <pre className="response-box">{JSON.stringify(submitted, null, 2)}</pre>}
                <StatusOverlay busy={busy} notice={status} errors={errors} />
            </form>
        </main>
    );
}

function DynamicInput({ field, value, onChange }) {
    if (field.type === 'section') {
        return (
            <section className="section-heading">
                <h2>{field.label}</h2>
                {field.help_text && <p>{field.help_text}</p>}
            </section>
        );
    }

    if (field.type === 'textarea') {
        return <FieldShell field={field}><textarea required={field.is_required} placeholder={field.placeholder || ''} value={value ?? unwrapDefault(field.default_value)} onChange={(event) => onChange(event.target.value)} /></FieldShell>;
    }

    if (field.type === 'dropdown' || field.type === 'select') {
        return (
            <FieldShell field={field}>
                <select required={field.is_required} value={value || ''} onChange={(event) => onChange(event.target.value)}>
                    <option value="">Select...</option>
                    {(field.options || []).map((option) => <option key={option} value={option}>{option}</option>)}
                </select>
            </FieldShell>
        );
    }

    if (field.type === 'radio') {
        return (
            <fieldset className="choice-set">
                <legend>{field.label}{field.is_required ? ' *' : ''}</legend>
                {field.help_text && <p className="muted small">{field.help_text}</p>}
                {(field.options || []).map((option) => (
                    <label key={option} className="checkline">
                        <input type="radio" name={field.key} required={field.is_required} checked={value === option} onChange={() => onChange(option)} />
                        {option}
                    </label>
                ))}
            </fieldset>
        );
    }

    if (field.type === 'checkbox') {
        return (
            <fieldset className="choice-set">
                <legend>{field.label}</legend>
                {(field.options || []).map((option) => (
                    <label key={option} className="checkline">
                        <input
                            type="checkbox"
                            checked={(value || []).includes(option)}
                            onChange={(event) => {
                                const current = value || [];
                                onChange(event.target.checked ? [...current, option] : current.filter((item) => item !== option));
                            }}
                        />
                        {option}
                    </label>
                ))}
            </fieldset>
        );
    }

    if (field.type === 'boolean') {
        return <label className="checkline"><input type="checkbox" checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />{field.label}</label>;
    }

    if (field.type === 'rating') {
        return (
            <FieldShell field={field}>
                <div className="rating-row">
                    {[1, 2, 3, 4, 5].map((rating) => (
                        <button type="button" key={rating} className={Number(value) === rating ? 'rating active' : 'rating'} onClick={() => onChange(rating)}>{rating}</button>
                    ))}
                </div>
            </FieldShell>
        );
    }

    if (field.type === 'file') {
        return (
            <FieldShell field={field}>
                <input
                    required={field.is_required}
                    type="file"
                    onChange={(event) => onChange(Array.from(event.target.files || []).map((file) => ({
                        name: file.name,
                        size: file.size,
                        type: file.type,
                    })))}
                />
            </FieldShell>
        );
    }

    return (
        <FieldShell field={field}>
            <input
                required={field.is_required}
                placeholder={field.placeholder || ''}
                type={field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : field.type === 'phone' ? 'tel' : 'text'}
                value={value ?? unwrapDefault(field.default_value)}
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldShell>
    );
}

function FieldShell({ field, children }) {
    return (
        <label className="input-group">
            {field.label}{field.is_required ? ' *' : ''}
            {children}
            {field.help_text && <span className="help-text">{field.help_text}</span>}
        </label>
    );
}

function TextInput({ label, value, onChange, type = 'text', placeholder = '', required = false, minLength, autoComplete }) {
    return (
        <label className="input-group">
            {label}
            <input
                type={type}
                value={value}
                placeholder={placeholder}
                required={required}
                minLength={minLength}
                autoComplete={autoComplete || (type === 'password' ? 'current-password' : type === 'email' ? 'email' : 'off')}
                onChange={(event) => onChange(event.target.value)}
            />
        </label>
    );
}

function StatusOverlay({ busy, notice, errors }) {
    if (!busy) return null;

    return (
        <div className="status-overlay" role="status" aria-live="polite">
            <div className="status-card">
                <div className="shimmer-line wide" />
                <div className="shimmer-line" />
                <div className="status-message">
                    <Loader2 className="spin" />
                    <span>{notice || 'Working...'}</span>
                </div>
                {errors?.length > 0 && <ErrorList errors={errors} />}
            </div>
        </div>
    );
}

function Metric({ label, value }) {
    return <div className="metric"><span>{label}</span><strong>{value}</strong></div>;
}

function formatAnswer(value) {
    if (value === null || value === undefined || value === '') return '-';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) {
        return value.map((item) => {
            if (item && typeof item === 'object') {
                return item.name || JSON.stringify(item);
            }
            return String(item);
        }).join(', ');
    }
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

function normalizeEditor(editor) {
    return {
        ...editor,
        slug: editor.slug || slugify(editor.title),
        store_submissions: editor.store_submissions !== false,
        fields: sanitizeFields(editor.fields || []).map((field) => ({
            ...field,
            options: field.options?.length ? field.options : undefined,
            validation_rules: field.validation_rules?.length ? field.validation_rules : undefined,
            default_value: unwrapDefault(field.default_value) || undefined,
        })),
    };
}

function resourceToEditor(resource) {
    return {
        title: resource.title,
        slug: resource.slug,
        description: resource.description || '',
        is_published: resource.is_published,
        store_submissions: resource.store_submissions !== false,
        settings: resource.settings || {},
        fields: sanitizeFields(resource.fields || []),
    };
}

function ErrorList({ errors }) {
    return (
        <div className="error-list">
            <strong>Fix these issues:</strong>
            {errors.map((error, index) => <p key={`${error}-${index}`}>{error}</p>)}
        </div>
    );
}

function validateAuthPayload(mode, payload) {
    const errors = [];
    const email = payload.email?.trim() || '';
    const password = payload.password || '';

    if (mode === 'register') {
        const name = payload.name?.trim() || '';

        if (!name) {
            errors.push('Name is required.');
        } else if (name.length < 2) {
            errors.push('Name must be at least 2 characters.');
        } else if (!/^[a-zA-Z][a-zA-Z\s.'-]{1,118}$/.test(name)) {
            errors.push('Name can contain only letters, spaces, apostrophes, dots, and hyphens.');
        }
    }

    if (!email) {
        errors.push('Email is required.');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        errors.push('Enter a valid email address.');
    }

    if (!password) {
        errors.push('Password is required.');
    } else if (password.length < 8) {
        errors.push('Password must be at least 8 characters.');
    } else if (/\s/.test(password)) {
        errors.push('Password must not contain spaces.');
    }

    return errors;
}

function splitLines(value) {
    return value
        .split(/\r\n|\r|\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function sanitizeFields(fields) {
    const seen = new Map();

    return fields.map((field, index) => {
        const label = field.label || field.key || `Field ${index + 1}`;
        const baseKey = slugify(field.key || label).replaceAll('-', '_') || `field_${index + 1}`;
        const count = seen.get(baseKey) || 0;
        seen.set(baseKey, count + 1);

        return {
            key: count ? `${baseKey}_${count + 1}` : baseKey,
            label,
            type: normalizeType(field.type, label),
            placeholder: field.placeholder || '',
            help_text: field.help_text || '',
            default_value: unwrapDefault(field.default_value),
            is_required: Boolean(field.is_required ?? field.required),
            options: normalizeOptions(field.options, field.type, label),
            validation_rules: Array.isArray(field.validation_rules) ? field.validation_rules.filter(Boolean) : [],
            section: field.section || '',
            step: field.step || '',
        };
    });
}

function normalizeType(type, label = '') {
    const normalized = String(type || 'text').toLowerCase().replace(/[\s-]+/g, '_');
    const aliases = {
        long_text: 'textarea',
        paragraph: 'textarea',
        integer: 'number',
        decimal: 'number',
        currency: 'number',
        rating: 'rating',
        select: 'dropdown',
        single_select: 'dropdown',
        multi_select: 'dropdown',
        multiselect: 'dropdown',
        multiple_choice: 'radio',
        checklist: 'checkbox',
        checkboxes: 'checkbox',
        yes_no: 'boolean',
        consent: 'boolean',
        agreement: 'boolean',
        phone: 'phone',
        tel: 'phone',
        file: 'file',
        upload: 'file',
        document: 'file',
        documents: 'file',
        section_heading: 'section',
        heading: 'section',
        stars: 'rating',
        star_rating: 'rating',
    };
    const mapped = aliases[normalized] || normalized;

    if (FIELD_TYPES.includes(mapped)) {
        return mapped;
    }

    const lowerLabel = label.toLowerCase();
    if (lowerLabel.includes('email')) return 'email';
    if (lowerLabel.includes('date')) return 'date';
    if (lowerLabel.includes('phone')) return 'phone';
    if (lowerLabel.includes('url') || lowerLabel.includes('website')) return 'url';
    if (lowerLabel.includes('checklist') || lowerLabel.includes('document')) return 'checkbox';
    if (lowerLabel.includes('income') || lowerLabel.includes('priority') || lowerLabel.includes('range')) return 'dropdown';
    if (lowerLabel.includes('consent') || lowerLabel.includes('agree')) return 'boolean';

    return 'text';
}

function normalizeOptions(options, type, label = '') {
    const normalizedType = normalizeType(type, label);

    if (!OPTION_TYPES.includes(normalizedType)) {
        return [];
    }

    if (Array.isArray(options)) {
        return options
            .map((option) => {
                if (typeof option === 'object' && option !== null) {
                    return option.label || option.value || '';
                }
                return String(option);
            })
            .map((option) => option.trim())
            .filter(Boolean);
    }

    if (typeof options === 'string') {
        return splitLines(options);
    }

    return [];
}

function unwrapDefault(value) {
    if (value && typeof value === 'object' && !Array.isArray(value) && Object.prototype.hasOwnProperty.call(value, 'value')) {
        return value.value ?? '';
    }

    return value ?? '';
}

function updateField(editor, setEditor, index, nextField) {
    setEditor({
        ...editor,
        fields: editor.fields.map((field, i) => (i === index ? nextField : field)),
    });
}

function slugify(value) {
    return String(value || 'generated-form')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

createRoot(document.getElementById('root')).render(<App />);
