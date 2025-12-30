// public/static/app.js
const API_BASE = '/api';

// 简单的 Toast 提示
function toast(msg, duration = 2000) {
    const el = document.createElement('div');
    el.className = 'toast';
    el.innerText = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), duration);
}

// 统一请求封装
async function request(endpoint, method = 'GET', data = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' }
    };
    if (data) opts.body = JSON.stringify(data);

    try {
        const res = await fetch(API_BASE + endpoint, opts);
        const json = await res.json();
        
// 未登录拦截
        if (json.code === 401) {
            // window.location.href = 'index.html'; // 删除或注释这一行
            // 让具体的页面逻辑（index.html）自己决定显示登录框，而不是强制刷新
            return json; // 把 401 结果返回回去
        }
        
        return json;
    } catch (e) {
        toast('网络错误');
        console.error(e);
        return { code: 500, msg: 'Network Error' };
    }
}

// 格式化金额 (分 -> 元)
function formatMoney(fen) {
    return (fen / 100).toFixed(2);
}