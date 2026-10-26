/* =============================================================================
 * CRM — boudy-elhegaz  |  crm.js
 * -----------------------------------------------------------------------------
 * مكتبة صغيرة مشتركة لكل صفحات الـCRM:
 *   crm.get(action, data, done, fail)   → usersc/scripts/show_data_.php
 *   crm.post(action, data, done, fail)  → usersc/scripts/data_in_.php  (+ csrf)
 *   crm.toast(type, msg)                → إشعار فوق على اليمين
 *   crm.confirm(msg, onYes, title)      → تأكيد بمودال Bootstrap
 *   crm.e(str) / crm.num(n) / crm.fmtDate(v) / crm.dt(v) → ص/م
 * بتعتمد على: jQuery + Bootstrap 5 (الاتنين موجودين في القالب).
 * ========================================================================== */
(function () {
    'use strict';

    var cfg = window.CRM || {};
    var $ = window.jQuery;

    /* ترجمة نصوص الـCRM — من كيس المفاتيح اللي بيبعته السيرفر
       (window.CRM.lang.strings) — وبترجع لاسم المفتاح لو ناقص */
    function T(key, fallback) {
        var s = window.CRM && window.CRM.lang && window.CRM.lang.strings;
        if (s && s[key]) { return s[key]; }
        return fallback || key;
    }

    /* ------------------------------------------------------- اتجاه الواجهة
     * بيقفل على لغة الموقع — <html lang> بيحطه اللودر من $_SESSION['us_lang']
     * (نفس قاعدة البلجن: عربي/فارسي/عبري... → RTL والباقي LTR)
     * ------------------------------------------------------------------- */
    var crmLangBase = String(document.documentElement.lang || 'en').toLowerCase().split(/[-_]/)[0];
    var crmDir = ['ar','he','fa','ur','sd','ps','dv','yi','ckb','ug'].indexOf(crmLangBase) !== -1
        ? 'rtl' : 'ltr';

    /* ------------------------------------------------------------ utils */

    function e(str) {
        return String(str === null || str === undefined ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function num(n) {
        var v = parseFloat(n);
        if (isNaN(v)) { return '0'; }
        return v.toLocaleString('en-US');
    }

    function fmtDate(val, withTime) {
        if (!val) { return '—'; }
        var s = String(val).replace(' ', 'T');
        var d = new Date(s);
        if (isNaN(d.getTime())) { return String(val); }
        var pad = function (x) { return (x < 10 ? '0' : '') + x; };
        var out = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        if (withTime !== false) { out += ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
        return out;
    }

    /* datetime "YYYY-MM-DD HH:MM(:SS)" → "YYYY-MM-DD h:MM ص/م" (12 ساعة زي الكاليندر)
       لو التاريخ من غير وقت أو فاضي → زي ما هو */
    function dt(val) {
        var v = String(val === null || val === undefined ? '' : val).trim();
        if (!v) { return ''; }
        var m = v.match(/^(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})/);
        if (!m) { return v; }
        var h  = parseInt(m[2], 10);
        var h12 = h % 12;
        if (h12 === 0) { h12 = 12; }
        return m[1] + ' ' + h12 + ':' + m[3] + (h < 12 ? ' ' + T('CRM_TIME_AM') : ' ' + T('CRM_TIME_PM'));
    }

    /* ----------------------------------------------------------- toasts */

    var swalIcons = { success: 'success', error: 'error', danger: 'error', warning: 'warning', info: 'info' };

    /*
     * SweetAlert2 v11 بتشيك `direction` بتاع العنصر عشان تفعّل وضع RTL.
     * القالب مش عامل dir="rtl" على html، فبنحقن الموديلات في حاوية RTL دائمة.
     */
    function swalTarget() {
        var el = document.getElementById('crmSwalTarget');
        if (!el) {
            el = document.createElement('div');
            el.id = 'crmSwalTarget';
            el.setAttribute('dir', crmDir);
            document.body.appendChild(el);
        }
        return '#crmSwalTarget';
    }

    function toast(type, msg) {
        if (!msg) { return; }

        /* الأولوية لـSweetAlert2: توست واضح فوق على اليمين يختفي لوحده */
        if (window.Swal) {
            window.Swal.fire({
                toast: true,
                target: swalTarget(),
                position: 'top-end',
                icon: swalIcons[type] || 'info',
                title: String(msg),
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true
            });
            return;
        }

        var host = document.getElementById('crmToastHost');
        if (!host) {
            host = document.createElement('div');
            host.id = 'crmToastHost';
            host.className = 'position-fixed top-0 end-0 p-3';
            host.style.zIndex = '10800';
            document.body.appendChild(host);
        }

        var colors = {
            success: 'text-bg-success',
            error: 'text-bg-danger',
            danger: 'text-bg-danger',
            warning: 'text-bg-warning',
            info: 'text-bg-info'
        };

        var el = document.createElement('div');
        el.className = 'toast align-items-center border-0 mb-2 ' + (colors[type] || colors.info);
        el.setAttribute('role', 'alert');
        el.innerHTML = '<div class="d-flex">' +
            '<div class="toast-body"></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div>';
        el.querySelector('.toast-body').textContent = msg;

        host.appendChild(el);

        if (window.bootstrap && window.bootstrap.Toast) {
            window.bootstrap.Toast.getOrCreateInstance(el, { delay: 4500 }).show();
        } else {
            /* بدون JS للـBootstrap: نضيف .show بنفسنا عشان التوست يبان مش يختفي فاضي */
            el.classList.add('show');
            setTimeout(function () { el.remove(); }, 4500);
        }
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    }

    /* ---------------------------------------------------------- confirm */

    function confirmBox(msg, onYes, title, yesLabel) {
        if (window.Swal) {
            window.Swal.fire({
                title: title || T('CRM_COMMON_CONFIRM'),
                text: msg || T('CRM_COMMON_ARE_YOU_SURE'),
                icon: 'question',
                target: swalTarget(),
                showCancelButton: true,
                confirmButtonText: yesLabel || T('CRM_COMMON_CONFIRM'),
                cancelButtonText: T('CRM_COMMON_CANCEL'),
                reverseButtons: true,
                focusConfirm: true
            }).then(function (r) {
                if (r.isConfirmed && typeof onYes === 'function') { onYes(); }
            });
            return;
        }

        var id = 'crmConfirmModal';
        var old = document.getElementById(id);
        if (old) { old.remove(); }

        var html = '' +
            '<div class="modal fade" id="' + id + '" tabindex="-1">' +
            '  <div class="modal-dialog modal-dialog-centered">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header">' +
            '        <h5 class="modal-title"></h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
            '      </div>' +
            '      <div class="modal-body"></div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' + e(T('CRM_COMMON_CANCEL')) + '</button>' +
            '        <button type="button" class="btn btn-danger crm-confirm-yes"></button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';

        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        var modalEl = wrap.firstChild;
        document.body.appendChild(modalEl);

        modalEl.querySelector('.modal-title').textContent = title || T('CRM_COMMON_CONFIRM');
        modalEl.querySelector('.modal-body').textContent = msg || T('CRM_COMMON_ARE_YOU_SURE');
        modalEl.querySelector('.crm-confirm-yes').textContent = yesLabel || T('CRM_COMMON_CONFIRM');

        var modal = window.bootstrap ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;

        modalEl.querySelector('.crm-confirm-yes').addEventListener('click', function () {
            if (modal) { modal.hide(); }
            if (typeof onYes === 'function') { onYes(); }
        });
        modalEl.addEventListener('hidden.bs.modal', function () { modalEl.remove(); });

        if (modal) { modal.show(); }
    }

    /* ----------------------------------------------------------- prompt */
    /*
     * موديل تعديل/إدخال في نص الشاشة (SweetAlert2):
     *   crm.prompt({
     *       title: 'عنوان', confirmLabel: 'حفظ',
     *       fields: [{
     *           name, label, value, placeholder, required,
     *           type: 'input'(الافتراضي) | 'textarea' | 'select' | 'datetime-local' | 'checkbox',
     *           options: [{ value, label }],          // لـ select بس
     *           requiredIf: function (vals) { ... }   // شرط إلزامي ديناميكي
     *       }]
     *   }, function (vals) { ... });   // vals = { nameField: 'قيمة', ... }
     */
    function promptModal(opts, onOk) {
        opts   = opts || {};
        var fields = opts.fields || [];
        var fpList = [];   /* نماذج flatpickr المفتوحة في الموديل ده (تتدمّر عند الإغلاق) */

        if (window.Swal) {
            var html = '<form id="crmSwalForm" dir="' + crmDir + '" class="text-start">' + fields.map(function (f) {
                /* checkbox: علامة صح/خطأ — قيمته 1 أو 0 */
                if (f.type === 'checkbox') {
                    return '<div class="mb-2 text-start">' +
                        '<div class="form-check">' +
                        '<input class="form-check-input" type="checkbox" name="' + e(f.name) + '" ' +
                        'id="crmSwalCb_' + e(f.name) + '"' + (f.value ? ' checked' : '') + '>' +
                        '<label class="form-check-label fw-bold" for="crmSwalCb_' + e(f.name) + '">' +
                        e(f.label || f.name) + '</label>' +
                        '</div>' +
                        (f.placeholder ? '<div class="form-text mt-0">' + e(f.placeholder) + '</div>' : '') +
                        '</div>';
                }
                var lbl = '<label class="form-label mb-1 fw-bold">' + e(f.label || f.name) + '</label>';
                var ctl;
                if (f.type === 'textarea') {
                    ctl = '<textarea class="swal2-textarea" name="' + e(f.name) + '" rows="3" ' +
                        'style="width:100%;margin:0" placeholder="' + e(f.placeholder || '') + '">' +
                        e(f.value || '') + '</textarea>';
                } else if (f.type === 'select') {
                    ctl = '<select class="swal2-select" name="' + e(f.name) + '" style="width:100%;margin:0">' +
                        '<option value="">' + e(f.placeholder || T('CRM_COMMON_SELECT')) + '</option>' +
                        (f.options || []).map(function (o) {
                            return '<option value="' + e(o.value) + '"' +
                                (String(o.value) === String(f.value || '') ? ' selected' : '') + '>' +
                                e(o.label) + '</option>';
                        }).join('') + '</select>';
                } else if (f.type === 'datetime-local') {
                    ctl = '<input type="datetime-local" class="swal2-input" name="' + e(f.name) + '" ' +
                        'style="width:100%;margin:0" value="' + e(f.value || '') + '">';
                } else {
                    ctl = '<input class="swal2-input" name="' + e(f.name) + '" style="width:100%;margin:0" ' +
                        'value="' + e(f.value || '') + '" placeholder="' + e(f.placeholder || '') + '">';
                }
                return '<div class="mb-2 text-start">' + lbl + ctl + '</div>';
            }).join('') + '</form>';

            window.Swal.fire({
                title: opts.title || T('CRM_COMMON_EDIT'),
                html: html,
                target: swalTarget(),
                showCancelButton: true,
                confirmButtonText: opts.confirmLabel || T('CRM_COMMON_SAVE'),
                cancelButtonText: T('CRM_COMMON_CANCEL'),
                focusConfirm: false,
                didOpen: function () {
                    /* لو flatpickr محمّل: حوّل حقول التاريخ لمنتقي كاليندر (زي الموبايل)
                       — وإلا يفضل <input type="datetime-local"> الأصلي زي ما هو */
                    if (!window.flatpickr) { return; }
                    var frm = document.getElementById('crmSwalForm');
                    if (!frm) { return; }
                    fields.forEach(function (f) {
                        if (f.type !== 'datetime-local') { return; }
                        var el = frm.querySelector('[name="' + f.name + '"]');
                        if (!el) { return; }
                        el.type = 'text';   /* نشيل السبينرز بتوع النوع الأصلي */
                        fpList.push(window.flatpickr(el, {
                            locale: (window.flatpickr.l10ns && window.flatpickr.l10ns.ar) ? 'ar' : undefined,
                            enableTime: true,
                            time_24hr: false,   /* 12 ساعة مع ص/م (اللغة العربية بتظبطها كده أصلًا) */
                            dateFormat: 'Y-m-d H:i',
                            disableMobile: true
                        }));
                    });
                },
                willClose: function () {
                    /* flatpickr بيحطّ الكاليندر في <body> — لازم نشيله قبل ما النافذة تتخرب
                       (وإلا بيفضل معلّق ويتراكم كاليندر ورا كل موديل) */
                    fpList.forEach(function (o) { try { o.destroy(); } catch (err) {} });
                    fpList = [];
                },
                preConfirm: function () {
                    var form = document.getElementById('crmSwalForm');
                    var out = {};
                    var missing = null;
                    fields.forEach(function (f) {
                        var el = form ? form.querySelector('[name="' + f.name + '"]') : null;
                        if (f.type === 'checkbox') {
                            out[f.name] = (el && el.checked) ? 1 : 0;
                            return;
                        }
                        out[f.name] = el ? String(el.value).trim() : '';
                        var need = f.required;
                        if (typeof f.requiredIf === 'function') { need = !!f.requiredIf(out); }
                        if (need && !out[f.name] && !missing) { missing = f.label || f.name; }
                    });
                    if (missing) {
                        window.Swal.showValidationMessage(T('CRM_ERR_REQUIRED_FIELD') + missing);
                        return false;
                    }
                    return out;
                }
            }).then(function (r) {
                if (r.isConfirmed && typeof onOk === 'function') { onOk(r.value); }
            });
            return;
        }

        /* fallback لو SweetAlert مش محمّل: prompts متتالية */
        var out = {};
        for (var i = 0; i < fields.length; i++) {
            var v = prompt(T('CRM_COMMON_EDIT') + ' ' + (fields[i].label || fields[i].name) + ':', fields[i].value || '');
            if (v === null) { return; }
            out[fields[i].name] = String(v).trim();
        }
        if (typeof onOk === 'function') { onOk(out); }
    }

    /* ------------------------------------------------------------- ajax */

    function send(method, url, payload, done, fail) {
        if (!$) {
            if (typeof fail === 'function') { fail(T('CRM_ERR_NO_JQUERY')); }
            return;
        }

        var data = $.extend({}, payload || {});
        if (method === 'POST') {
            data.csrf = cfg.token || '';
        } else {
            data._t = Date.now();
        }

        return $.ajax({
            url: url,
            method: method,
            data: data,
            dataType: 'json',
            cache: false
        }).done(function (res) {
            if (res && res.success) {
                if (res.msg) { toast('success', res.msg); }
                if (typeof done === 'function') { done(res.data, res); }
            } else {
                var m = (res && res.msg) ? res.msg : T('CRM_COMMON_UNEXPECTED');
                toast('error', m);
                if (typeof fail === 'function') { fail(m, res); }
            }
        }).fail(function (xhr) {
            var m = T('CRM_COMMON_CONN_FAIL');
            if (xhr && xhr.responseJSON && xhr.responseJSON.msg) {
                m = xhr.responseJSON.msg;
            } else if (xhr && xhr.status === 403) {
                m = T('CRM_COMMON_NO_PERM');
            } else if (xhr && xhr.status === 419) {
                m = T('CRM_COMMON_SESSION_END');
            }
            toast('error', m);
            if (typeof fail === 'function') { fail(m, xhr); }
        });
    }

    function get(action, data, done, fail) {
        var payload = $.extend({}, data || {}, { action: action });
        return send('GET', cfg.urls ? cfg.urls.show : 'show_data_.php', payload, done, fail);
    }

    function post(action, data, done, fail) {
        var payload = $.extend({}, data || {}, { action: action });
        return send('POST', cfg.urls ? cfg.urls.in : 'data_in_.php', payload, done, fail);
    }

    /* --------------------------------------------------------- csv export */

    /* تصدير ملف CSV بـBOM (عشان إكسل العربي) — rows = مصفوفة مصفوفات نصوص
       (الصف الأول = العناوين) */
    function csvExport(filename, rows) {
        var csv = (rows || []).map(function (r) {
            return (r || []).map(function (c) {
                c = String(c == null ? '' : c);
                return '"' + c.replace(/"/g, '""') + '"';
            }).join(',');
        }).join('\r\n');

        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename || 'export.csv';
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { a.remove(); URL.revokeObjectURL(url); }, 0);
    }

    /* ------------------------------------------------------------ export */

    window.crm = {
        cfg: cfg,
        get: get,
        post: post,
        toast: toast,
        confirm: confirmBox,
        prompt: promptModal,
        e: e,
        num: num,
        fmtDate: fmtDate,
        dt: dt,
        csvExport: csvExport
    };

    /* ترجمة سريعة للصفحات: crmT('CRM_COMMON_SAVE') */
    window.crmT = T;
})();
