import fs from 'fs';
import path from 'path';
import crypto from 'crypto';

const API_URL = 'http://localhost:8000/api';
let creatorToken = '';
let adminToken = '';
let withdrawalId = null;

async function api(endpoint, method = 'GET', body = null, token = null, isFormData = false) {
    const headers = {};
    if (token) headers['Authorization'] = `Bearer ${token}`;
    if (!isFormData && body) headers['Content-Type'] = 'application/json';

    const options = { method, headers };
    if (body && !isFormData) options.body = JSON.stringify(body);
    if (body && isFormData) options.body = body;

    const res = await fetch(`${API_URL}${endpoint}`, options);
    const text = await res.text();
    try { return JSON.parse(text); } catch { return text; }
}

async function runTest() {
    console.log('--- 1. LOGIN CREATOR ---');
    let res = await api('/login', 'POST', { email: 'siswa1@gmail.com', password: 'password' });
    if (res.success) {
        creatorToken = res.data.token;
        console.log('✅ Creator Login berhasil.');
    } else {
        console.log('❌ Creator Login gagal:', res);
        return;
    }

    console.log('\n--- 2. LOGIN ADMIN ---');
    res = await api('/login', 'POST', { email: 'admin@amania.id', password: 'password' });
    if (res.success) {
        adminToken = res.data.token;
        console.log('✅ Admin Login berhasil.');
    } else {
        console.log('❌ Admin Login gagal:', res);
        return;
    }

    console.log('\n--- 3. CEK SALDO CREATOR ---');
    res = await api('/withdrawals/stats', 'GET', null, creatorToken);
    console.log('Stats FULL RES:', res);
    
    // Simulate that the creator has at least 50000 balance for testing.
    // Actually, if the balance is 0, we can't test it. Let's just create a dummy CourseEnrollment if needed.
    
    console.log('\n--- 4. REQUEST WITHDRAWAL ---');
    res = await api('/withdrawals', 'POST', {
        amount: 50000,
        bank_name: 'BCA',
        bank_account_name: 'Testing Creator',
        bank_account_number: '1234567890'
    }, creatorToken);
    
    if (res.success) {
        console.log('✅ Withdrawal requested:', res.data.id);
        withdrawalId = res.data.id;
    } else {
        console.log('ℹ️ Gagal request (Mungkin saldo tidak cukup):', res.message);
        // We will just bypass this and manually create one in DB if needed, but let's see.
    }
}

runTest();
