import fs from 'fs';

const API_URL = 'http://localhost:8000/api';
let token = '';
let taskId = null;
let subtaskId = null;
let commentId = null;

async function login() {
    console.log('--- LOGIN ---');
    const res = await fetch(`${API_URL}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: 'admin@amania.id', password: 'password' })
    });
    const json = await res.json();
    if (json.success && json.data.token) {
        token = json.data.token;
        console.log('✅ Login berhasil. Token didapatkan.');
    } else {
        console.error('❌ Login gagal:', json);
        process.exit(1);
    }
}

async function api(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
            ...(body ? { 'Content-Type': 'application/json' } : {})
        },
        ...(body ? { body: JSON.stringify(body) } : {})
    };
    const res = await fetch(`${API_URL}${endpoint}`, options);
    return res.json();
}

async function runTests() {
    await login();

    console.log('\n--- 1. GET ALL TASKS ---');
    let res = await api('/admin/tasks');
    console.log(res.success ? '✅ Berhasil fetch tasks' : '❌ Gagal fetch tasks', res.stats);

    console.log('\n--- 2. CREATE TASK ---');
    res = await api('/admin/tasks', 'POST', {
        title: 'Script Test Task (With Time)',
        description: 'Testing via Node.js script',
        priority: 'high',
        label_name: 'Testing',
        label_color: '#EF4444',
        status: 'pending',
        due_date: '2026-12-31 23:59:00', // Waktu + Jam
        subtasks: [{ title: 'Subtask otomatis 1' }]
    });
    if (res.success) {
        taskId = res.data.id;
        console.log('✅ Task dibuat dengan ID:', taskId);
    } else {
        console.error('❌ Gagal membuat task:', res);
        return;
    }

    console.log('\n--- 3. ADD SUBTASK ---');
    res = await api(`/admin/tasks/${taskId}/subtasks`, 'POST', { title: 'Subtask 1' });
    if (res.success) {
        subtaskId = res.data.id;
        console.log('✅ Subtask ditambahkan dengan ID:', subtaskId);
    } else {
        console.error('❌ Gagal menambah subtask:', res);
    }

    console.log('\n--- 4. TOGGLE SUBTASK ---');
    res = await api(`/admin/tasks/${taskId}/subtasks/${subtaskId}/toggle`, 'POST');
    console.log(res.success ? '✅ Subtask ditoggle' : '❌ Gagal toggle subtask');

    console.log('\n--- 5. ADD COMMENT ---');
    res = await api(`/admin/tasks/${taskId}/comments`, 'POST', { body: 'Ini komentar test' });
    if (res.success) {
        commentId = res.data.id;
        console.log('✅ Comment ditambahkan dengan ID:', commentId);
    } else {
        console.error('❌ Gagal menambah comment:', res);
    }

    console.log('\n--- 6. MOVE TASK (KANBAN) ---');
    res = await api(`/admin/tasks/${taskId}/move`, 'POST', { status: 'in_progress', position: 1 });
    console.log(res.success ? '✅ Task dipindahkan ke in_progress' : '❌ Gagal move task');

    console.log('\n--- 7. FETCH TASKS AGAIN (CHECK PROGRESS) ---');
    res = await api('/admin/tasks');
    const task = res.data.find(t => t.id === taskId);
    console.log('✅ Progress Task:', task.subtask_progress, '%, Status:', task.status, 'Comments:', task.comments_count);

    console.log('\n--- 8. DELETE TASK ---');
    res = await api(`/admin/tasks/${taskId}`, 'DELETE');
    console.log(res.success ? '✅ Task berhasil dihapus (beserta subtask & comment)' : '❌ Gagal menghapus task');
}

runTests();
