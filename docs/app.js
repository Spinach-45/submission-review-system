/*
 * 投稿審查系統：GitHub Pages 展示版
 * 與 PHP 版相同的頁面與狀態流程，資料改存在瀏覽器 localStorage。
 * 正式版的權限與狀態檢查在 PHP 後端（includes/manuscripts.php）。
 */
(function () {
    'use strict';

    const STORE_KEY = 'srs-demo-v1';
    const SESSION_KEY = 'srs-demo-user';

    const STATUS_LABELS = { editing: '編輯中', reviewing: '審查中', approved: '已通過' };
    const ACTION_LABELS = { submit: '送出投稿', approve: '審查通過', reject: '退回修改' };

    const USERS = [
        { id: 1, username: 'alice', password: 'author123', display_name: '王小明', role: 'author' },
        { id: 2, username: 'bob', password: 'author123', display_name: '李小華', role: 'author' },
        { id: 3, username: 'editor', password: 'editor123', display_name: '陳主編', role: 'editor' },
    ];
    const CATEGORIES = [
        { id: 1, name: '科技' }, { id: 2, name: '生活' }, { id: 3, name: '旅遊' },
        { id: 4, name: '文學' }, { id: 5, name: '評論' },
    ];

    /* ---------- 資料 ---------- */

    function now() {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`;
    }

    function seed() {
        const t = now();
        return {
            nextId: 6,
            manuscripts: [
                { id: 1, author_id: 1, category_id: 1, title: '生成式 AI 對大學教育的影響', content: '近年生成式 AI 快速普及，學生的學習方式也隨之改變……', status: 'editing', submitted_at: null, updated_at: t },
                { id: 2, author_id: 1, category_id: 3, title: '台東三天兩夜慢旅行', content: '第一天從台北搭火車出發，沿著東海岸一路向南……', status: 'reviewing', submitted_at: t, updated_at: t },
                { id: 3, author_id: 1, category_id: 2, title: '租屋族的省錢料理', content: '一只電鍋就能完成的五道料理……', status: 'approved', submitted_at: t, updated_at: t },
                { id: 4, author_id: 2, category_id: 4, title: '雨夜', content: '雨落在巷口的鐵皮屋頂上，像一首聽了很多年的歌……', status: 'reviewing', submitted_at: t, updated_at: t },
                { id: 5, author_id: 2, category_id: 5, title: '從學生視角看校園數位化', content: '選課系統、線上繳費、行動學生證……', status: 'editing', submitted_at: null, updated_at: t },
            ],
            logs: [
                { manuscript_id: 2, user_id: 1, action: 'submit', comment: null, created_at: t },
                { manuscript_id: 3, user_id: 1, action: 'submit', comment: null, created_at: t },
                { manuscript_id: 3, user_id: 3, action: 'approve', comment: '內容實用，排版清楚。', created_at: t },
                { manuscript_id: 4, user_id: 2, action: 'submit', comment: null, created_at: t },
            ],
        };
    }

    let db = load();

    function load() {
        try {
            const data = JSON.parse(localStorage.getItem(STORE_KEY));
            if (data && Array.isArray(data.manuscripts)) return data;
        } catch (e) { /* 資料損毀時重設 */ }
        const data = seed();
        localStorage.setItem(STORE_KEY, JSON.stringify(data));
        return data;
    }

    function save() {
        localStorage.setItem(STORE_KEY, JSON.stringify(db));
    }

    const userById = id => USERS.find(u => u.id === id);
    const categoryName = id => (CATEGORIES.find(c => c.id === id) || {}).name || '';
    const findManuscript = id => db.manuscripts.find(m => m.id === id) || null;

    function currentUser() {
        const id = Number(sessionStorage.getItem(SESSION_KEY));
        return userById(id) || null;
    }

    /** 狀態轉換：只有目前狀態正確（且作者相符）才會成功，與 PHP 版相同規則 */
    function transition(id, from, to, action, userId, authorId, comment) {
        const m = findManuscript(id);
        if (!m || m.status !== from || (authorId != null && m.author_id !== authorId)) return false;
        m.status = to;
        m.updated_at = now();
        if (action === 'submit') m.submitted_at = m.updated_at;
        db.logs.push({ manuscript_id: id, user_id: userId, action, comment: comment || null, created_at: m.updated_at });
        save();
        return true;
    }

    /* ---------- 畫面工具 ---------- */

    const app = document.getElementById('app');
    const nav = document.getElementById('nav');
    let flashMsg = null;

    function h(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    const nl2br = s => h(s).replace(/\n/g, '<br>');
    const badge = s => `<span class="badge badge-${s}">${STATUS_LABELS[s]}</span>`;

    function flash(message, type = 'success') { flashMsg = { message, type }; }

    function go(path) {
        if (location.hash === '#' + path) render();
        else location.hash = path;
    }

    function homePath(user) { return user.role === 'editor' ? '/editor' : '/author'; }

    function renderNav(user) {
        if (!user) { nav.innerHTML = ''; return; }
        const links = user.role === 'author'
            ? '<a href="#/author">我的投稿</a><a href="#/author/new">新增投稿</a>'
            : '<a href="#/editor">待審查清單</a>';
        nav.innerHTML = `${links}
            <span class="user-chip">${h(user.display_name)}（${user.role === 'editor' ? '編輯' : '作者'}）</span>
            <button type="button" class="btn btn-link" data-action="logout">登出</button>`;
    }

    function page(html) {
        const f = flashMsg;
        flashMsg = null;
        app.innerHTML = (f ? `<div class="alert alert-${f.type}">${h(f.message)}</div>` : '') + html;
        window.scrollTo(0, 0);
    }

    function reviewHistory(id) {
        const logs = db.logs.filter(l => l.manuscript_id === id).slice().reverse();
        if (!logs.length) return '';
        return `<section class="card"><h2>審查歷程</h2><ol class="timeline">${logs.map(l => `
            <li class="timeline-${l.action}">
                <div><strong>${ACTION_LABELS[l.action]}</strong> · ${h(userById(l.user_id).display_name)}
                    <span class="muted">${h(l.created_at)}</span></div>
                ${l.comment ? `<div class="comment">${nl2br(l.comment)}</div>` : ''}
            </li>`).join('')}</ol></section>`;
    }

    function latestReject(id) {
        const logs = db.logs.filter(l => l.manuscript_id === id);
        const last = logs[logs.length - 1];
        return last && last.action === 'reject' ? last : null;
    }

    /* ---------- 頁面 ---------- */

    function loginPage(error, username = '') {
        page(`<section class="card login-card">
            <h1>登入</h1>
            ${error ? `<div class="alert alert-error">${h(error)}</div>` : ''}
            <form class="form" id="login-form">
                <label>帳號 <input type="text" name="username" value="${h(username)}" required autofocus></label>
                <label>密碼 <input type="password" name="password" required></label>
                <button type="submit" class="btn btn-primary btn-block">登入</button>
            </form>
            <div class="demo-accounts">
                <p>測試帳號（點一下自動填入）</p>
                <ul>
                    <li>作者：<button type="button" class="link" data-fill="alice:author123"><code>alice</code> / <code>author123</code></button></li>
                    <li>作者：<button type="button" class="link" data-fill="bob:author123"><code>bob</code> / <code>author123</code></button></li>
                    <li>編輯：<button type="button" class="link" data-fill="editor:editor123"><code>editor</code> / <code>editor123</code></button></li>
                </ul>
            </div>
        </section>`);
    }

    function authorList(user, query) {
        const status = query.get('status') || '';
        const category = Number(query.get('category') || 0);
        const items = db.manuscripts
            .filter(m => m.author_id === user.id)
            .filter(m => !status || m.status === status)
            .filter(m => !category || m.category_id === category)
            .sort((a, b) => b.updated_at.localeCompare(a.updated_at) || b.id - a.id);

        const rows = items.map(m => `<tr>
            <td data-label="類別">${h(categoryName(m.category_id))}</td>
            <td data-label="標題"><a href="#/author/view/${m.id}">${h(m.title)}</a></td>
            <td data-label="狀態">${badge(m.status)}</td>
            <td data-label="更新">${h(m.updated_at)}</td>
            <td class="actions">${m.status === 'editing'
                ? `<a class="btn btn-sm" href="#/author/edit/${m.id}">編輯</a>
                   <button class="btn btn-sm btn-primary" data-action="submit" data-id="${m.id}">送出</button>
                   <button class="btn btn-sm btn-danger" data-action="delete" data-id="${m.id}">刪除</button>`
                : `<a class="btn btn-sm" href="#/author/view/${m.id}">檢視</a>`}</td>
        </tr>`).join('');

        page(`<div class="page-head"><h1>我的投稿</h1><a class="btn btn-primary" href="#/author/new">＋ 新增投稿</a></div>
            <form class="filters" id="filters">
                <select name="status"><option value="">全部狀態</option>
                    ${Object.entries(STATUS_LABELS).map(([k, v]) => `<option value="${k}" ${status === k ? 'selected' : ''}>${v}</option>`).join('')}
                </select>
                <select name="category"><option value="">全部類別</option>
                    ${CATEGORIES.map(c => `<option value="${c.id}" ${category === c.id ? 'selected' : ''}>${h(c.name)}</option>`).join('')}
                </select>
            </form>
            ${items.length ? `<div class="table-wrap"><table class="table">
                <thead><tr><th>類別</th><th>標題</th><th>審查狀態</th><th>最後更新</th><th class="actions">操作</th></tr></thead>
                <tbody>${rows}</tbody></table></div>` : '<div class="empty">目前沒有稿件。</div>'}`);
    }

    function authorForm(user, id, errors = [], input = null) {
        let m = { category_id: '', title: '', content: '' };
        if (id) {
            const found = findManuscript(id);
            if (!found || found.author_id !== user.id || found.status !== 'editing') {
                flash('找不到稿件，或稿件已送出審查無法編輯。', 'error');
                return go('/author');
            }
            m = found;
        }
        if (input) m = Object.assign({}, m, input);
        const rejected = id ? latestReject(id) : null;
        const title = id ? '編輯投稿' : '新增投稿';
        page(`<h1>${title}</h1>
            ${rejected && rejected.comment ? `<div class="alert alert-warning"><strong>編輯退回意見：</strong>${nl2br(rejected.comment)}</div>` : ''}
            ${errors.length ? `<div class="alert alert-error"><ul>${errors.map(e => `<li>${h(e)}</li>`).join('')}</ul></div>` : ''}
            <form class="form card" id="manuscript-form" data-id="${id || ''}">
                <label>類別
                    <select name="category_id" required><option value="">請選擇</option>
                        ${CATEGORIES.map(c => `<option value="${c.id}" ${Number(m.category_id) === c.id ? 'selected' : ''}>${h(c.name)}</option>`).join('')}
                    </select>
                </label>
                <label>標題 <input type="text" name="title" maxlength="200" value="${h(m.title)}" required></label>
                <label>文章內容 <textarea name="content" rows="14" required>${h(m.content)}</textarea></label>
                <div class="form-actions">
                    <a class="btn" href="#/author">取消</a>
                    <button type="submit" class="btn btn-primary">儲存</button>
                </div>
            </form>`);
    }

    function authorView(user, id) {
        const m = findManuscript(id);
        if (!m || m.author_id !== user.id) { flash('找不到稿件。', 'error'); return go('/author'); }
        page(`<a class="back" href="#/author">← 回到我的投稿</a>
            <article class="card manuscript">
                <div class="meta"><span class="tag">${h(categoryName(m.category_id))}</span>${badge(m.status)}</div>
                <h1>${h(m.title)}</h1>
                <div class="content">${nl2br(m.content)}</div>
                ${m.status === 'editing'
                    ? `<div class="form-actions">
                        <a class="btn" href="#/author/edit/${m.id}">編輯</a>
                        <button class="btn btn-danger" data-action="delete" data-id="${m.id}">刪除</button>
                        <button class="btn btn-primary" data-action="submit" data-id="${m.id}">送出投稿</button>
                       </div>`
                    : `<p class="hint">此稿件${STATUS_LABELS[m.status]}，無法再修改。</p>`}
            </article>${reviewHistory(m.id)}`);
    }

    function editorList() {
        const items = db.manuscripts.filter(m => m.status === 'reviewing')
            .sort((a, b) => (a.submitted_at || '').localeCompare(b.submitted_at || '') || a.id - b.id);
        const rows = items.map(m => `<tr>
            <td data-label="作者">${h(userById(m.author_id).display_name)}</td>
            <td data-label="類別">${h(categoryName(m.category_id))}</td>
            <td data-label="標題"><a href="#/editor/review/${m.id}">${h(m.title)}</a></td>
            <td data-label="送出">${h(m.submitted_at)}</td>
            <td class="actions"><a class="btn btn-sm btn-primary" href="#/editor/review/${m.id}">審查</a></td>
        </tr>`).join('');
        page(`<div class="page-head"><h1>待審查清單</h1><span class="count">共 ${items.length} 篇</span></div>
            ${items.length ? `<div class="table-wrap"><table class="table">
                <thead><tr><th>作者</th><th>類別</th><th>標題</th><th>送出時間</th><th class="actions">操作</th></tr></thead>
                <tbody>${rows}</tbody></table></div>` : '<div class="empty">目前沒有待審查的稿件。</div>'}`);
    }

    function editorReview(id) {
        const m = findManuscript(id);
        if (!m || m.status === 'editing') { flash('找不到稿件。', 'error'); return go('/editor'); }
        page(`<a class="back" href="#/editor">← 回到待審查清單</a>
            <article class="card manuscript">
                <div class="meta"><span class="tag">${h(categoryName(m.category_id))}</span>${badge(m.status)}
                    <span class="muted">作者：${h(userById(m.author_id).display_name)}</span></div>
                <h1>${h(m.title)}</h1>
                <div class="content">${nl2br(m.content)}</div>
            </article>
            ${m.status === 'reviewing' ? `<form class="card form review-form" id="review-form" data-id="${m.id}">
                <h2>審查決定</h2>
                <label>審查意見（退回時建議填寫，作者會看到）
                    <textarea name="comment" rows="4" placeholder="例如：第二段論點需要補充資料來源。"></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" name="decision" value="reject" class="btn btn-danger">退回稿件</button>
                    <button type="submit" name="decision" value="approve" class="btn btn-success">通過</button>
                </div>
            </form>` : ''}${reviewHistory(m.id)}`);
    }

    /* ---------- 路由 ---------- */

    function render() {
        const [path, qs] = (location.hash.slice(1) || '/').split('?');
        const query = new URLSearchParams(qs || '');
        const parts = path.split('/').filter(Boolean);
        const user = currentUser();
        db = load(); // 其他分頁可能改過資料
        renderNav(user);

        if (parts[0] === 'login' || !user) {
            if (user) return go(homePath(user));
            if (parts[0] !== 'login') {
                // 未登入：直接顯示登入頁並改寫網址，不另外觸發 hashchange
                if (parts.length) flash('請先登入。', 'error');
                history.replaceState(null, '', '#/login');
            }
            return loginPage();
        }
        if (!parts.length) return go(homePath(user));

        // 角色不符的頁面一律擋下
        if (parts[0] !== user.role) {
            return page('<div class="alert alert-error">403 Forbidden：你沒有權限存取此頁面。</div>');
        }
        const id = Number(parts[2]) || null;
        if (user.role === 'author') {
            if (parts[1] === 'new') return authorForm(user, null);
            if (parts[1] === 'edit') return authorForm(user, id);
            if (parts[1] === 'view') return authorView(user, id);
            return authorList(user, query);
        }
        if (parts[1] === 'review') return editorReview(id);
        return editorList();
    }

    /* ---------- 事件 ---------- */

    document.addEventListener('submit', e => {
        const form = e.target;
        const user = currentUser();

        if (form.id === 'login-form') {
            e.preventDefault();
            const username = form.username.value.trim();
            const found = USERS.find(u => u.username === username && u.password === form.password.value);
            if (!found) return loginPage('帳號或密碼錯誤。', username);
            sessionStorage.setItem(SESSION_KEY, String(found.id));
            flash(`歡迎回來，${found.display_name}！`);
            return go(homePath(found));
        }

        if (form.id === 'manuscript-form' && user && user.role === 'author') {
            e.preventDefault();
            const id = Number(form.dataset.id) || null;
            const input = {
                category_id: Number(form.category_id.value),
                title: form.title.value.trim(),
                content: form.content.value.trim(),
            };
            const errors = [];
            if (!CATEGORIES.some(c => c.id === input.category_id)) errors.push('請選擇類別。');
            if (!input.title) errors.push('標題不可空白。');
            if (!input.content) errors.push('文章內容不可空白。');
            if (errors.length) return authorForm(user, id, errors, input);

            if (id) {
                const m = findManuscript(id);
                if (!m || m.author_id !== user.id || m.status !== 'editing') {
                    flash('稿件已送出審查，無法再編輯。', 'error');
                    return go('/author');
                }
                Object.assign(m, input, { updated_at: now() });
                flash('稿件已儲存。');
                save();
                return go('/author/view/' + id);
            }
            const newId = db.nextId++;
            db.manuscripts.push(Object.assign({ id: newId, author_id: user.id, status: 'editing', submitted_at: null, updated_at: now() }, input));
            save();
            flash('已新增稿件（狀態：編輯中）。');
            return go('/author/view/' + newId);
        }

        if (form.id === 'review-form' && user && user.role === 'editor') {
            e.preventDefault();
            const decision = e.submitter ? e.submitter.value : '';
            const id = Number(form.dataset.id);
            const comment = form.comment.value.trim();
            if (decision === 'approve') {
                if (!confirm('確定通過這篇稿件？')) return;
                const ok = transition(id, 'reviewing', 'approved', 'approve', user.id, null, comment);
                flash(ok ? '稿件已通過。' : '只能審查「審查中」的稿件。', ok ? 'success' : 'error');
            } else if (decision === 'reject') {
                if (!confirm('確定退回這篇稿件？')) return;
                const ok = transition(id, 'reviewing', 'editing', 'reject', user.id, null, comment);
                flash(ok ? '稿件已退回，狀態改為「編輯中」。' : '只能審查「審查中」的稿件。', ok ? 'success' : 'error');
            }
            return go('/editor');
        }
    });

    document.addEventListener('change', e => {
        const form = e.target.form;
        if (form && form.id === 'filters') {
            const params = new URLSearchParams();
            if (form.status.value) params.set('status', form.status.value);
            if (form.category.value) params.set('category', form.category.value);
            go('/author' + (params.toString() ? '?' + params : ''));
        }
    });

    document.addEventListener('click', e => {
        const fill = e.target.closest('[data-fill]');
        if (fill) {
            const [u, p] = fill.dataset.fill.split(':');
            const form = document.getElementById('login-form');
            form.username.value = u;
            form.password.value = p;
            return;
        }

        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const user = currentUser();
        const action = btn.dataset.action;
        const id = Number(btn.dataset.id);

        if (action === 'logout') {
            sessionStorage.removeItem(SESSION_KEY);
            flash('已登出。');
            return go('/login');
        }
        if (!user || user.role !== 'author') return;

        if (action === 'submit') {
            if (!confirm('送出後將無法修改，確定送出審查？')) return;
            const ok = transition(id, 'editing', 'reviewing', 'submit', user.id, user.id);
            flash(ok ? '已送出投稿，狀態改為「審查中」。' : '只能送出自己「編輯中」的稿件。', ok ? 'success' : 'error');
            return go('/author');
        }
        if (action === 'delete') {
            if (!confirm('確定刪除這篇稿件？')) return;
            const m = findManuscript(id);
            if (m && m.author_id === user.id && m.status === 'editing') {
                db.manuscripts = db.manuscripts.filter(x => x.id !== id);
                db.logs = db.logs.filter(l => l.manuscript_id !== id);
                save();
                flash('稿件已刪除。');
            } else {
                flash('只能刪除自己「編輯中」的稿件。', 'error');
            }
            return go('/author');
        }
    });

    document.getElementById('reset-demo').addEventListener('click', () => {
        if (!confirm('要把展示資料重設回初始狀態嗎？')) return;
        db = seed();
        save();
        flash('展示資料已重設。');
        render();
    });

    window.addEventListener('hashchange', render);
    // 另一個分頁修改資料時，自動更新畫面
    window.addEventListener('storage', e => { if (e.key === STORE_KEY) render(); });
    render();
})();
