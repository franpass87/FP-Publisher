(()=>{var f=(u,t)=>()=>(t||u((t={exports:{}}).exports,t),t.exports);var p=f((g,l)=>{var d=class{constructor(){this.init()}init(){this.bindEvents(),this.enhanceExistingElements(),this.addCustomStyles()}addCustomStyles(){if(document.getElementById("tts-admin-utils-styles"))return;let t=document.createElement("style");t.id="tts-admin-utils-styles",t.textContent=`
            /* Enhanced Modal Styles */
            .tts-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                opacity: 0;
                transition: opacity 0.3s ease;
                backdrop-filter: blur(2px);
            }
            
            .tts-modal-overlay.show {
                opacity: 1;
            }
            
            .tts-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.9);
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 90%;
                z-index: 100001;
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.320, 1.275);
            }
            
            .tts-modal.show {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            
            .tts-modal-header {
                padding: 20px 20px 0 20px;
                border-bottom: 1px solid #f0f0f1;
                margin-bottom: 20px;
            }
            
            .tts-modal-title {
                margin: 0 0 10px 0;
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .tts-modal-body {
                padding: 0 20px 20px 20px;
                color: #50575e;
                line-height: 1.5;
            }
            
            .tts-modal-footer {
                padding: 20px;
                border-top: 1px solid #f0f0f1;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            
            .tts-modal-close {
                position: absolute;
                top: 15px;
                right: 15px;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #666;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            
            .tts-modal-close:hover {
                background: #f0f0f1;
                color: #333;
            }
            
            /* Enhanced Buttons */
            .tts-btn-enhanced {
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            
            .tts-btn-enhanced::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }
            
            .tts-btn-enhanced:hover::before {
                width: 300px;
                height: 300px;
            }
            
            /* Loading States */
            .tts-loading-btn {
                position: relative;
                pointer-events: none;
                opacity: 0.7;
            }
            
            .tts-loading-btn::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 16px;
                height: 16px;
                margin: -8px 0 0 -8px;
                border: 2px solid transparent;
                border-top: 2px solid currentColor;
                border-radius: 50%;
                animation: tts-spin 1s linear infinite;
            }
            
            @keyframes tts-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Progress Bars */
            .tts-progress {
                width: 100%;
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                margin: 10px 0;
            }
            
            .tts-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #135e96, #2271b1);
                width: 0%;
                transition: width 0.3s ease;
                position: relative;
            }
            
            .tts-progress-bar::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
                animation: tts-progress-shine 1.5s infinite;
            }
            
            @keyframes tts-progress-shine {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            
            /* Enhanced Tables */
            .tts-enhanced-table {
                background: #fff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            
            .tts-enhanced-table th {
                background: #f8f9fa;
                font-weight: 600;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            
            .tts-enhanced-table tr:hover {
                background: #f8f9fa;
            }
            
            .tts-enhanced-table .row-actions {
                opacity: 0;
                transition: opacity 0.2s;
            }
            
            .tts-enhanced-table tr:hover .row-actions {
                opacity: 1;
            }
            
            /* Bulk Actions */
            .tts-bulk-actions {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 15px;
                margin: 15px 0;
                display: none;
            }
            
            .tts-bulk-actions.show {
                display: block;
                animation: tts-fadeInDown 0.3s ease;
            }
            
            @keyframes tts-fadeInDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Status Badges */
            .tts-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .tts-status-badge.success {
                background: rgba(0, 163, 42, 0.1);
                color: #00a32a;
            }
            
            .tts-status-badge.error {
                background: rgba(214, 54, 56, 0.1);
                color: #d63638;
            }
            
            .tts-status-badge.warning {
                background: rgba(245, 110, 40, 0.1);
                color: #f56e28;
            }
            
            .tts-status-badge.info {
                background: rgba(19, 94, 150, 0.1);
                color: #135e96;
            }
            
            /* Search and Filter Enhancements */
            .tts-search-container {
                position: relative;
                display: inline-block;
            }
            
            .tts-search-input {
                padding-left: 35px;
                border-radius: 20px;
                border: 1px solid #ddd;
                transition: all 0.3s ease;
            }
            
            .tts-search-input:focus {
                border-color: #135e96;
                box-shadow: 0 0 0 3px rgba(19, 94, 150, 0.1);
            }
            
            .tts-search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #666;
                pointer-events: none;
            }
            
            /* Responsive Enhancements */
            @media (max-width: 768px) {
                .tts-modal {
                    width: 95%;
                    margin: 20px;
                }
                
                .tts-modal-footer {
                    flex-direction: column;
                }
                
                .tts-modal-footer .tts-btn {
                    width: 100%;
                    margin-bottom: 5px;
                }
            }
        `,document.head.appendChild(t)}bindEvents(){document.addEventListener("change",t=>{t.target.matches(".tts-bulk-select-all")?this.handleBulkSelectAll(t.target):t.target.matches(".tts-bulk-select-item")&&this.handleBulkSelectItem()}),document.addEventListener("submit",t=>{t.target.matches(".tts-enhanced-form")&&this.handleEnhancedForm(t)}),document.addEventListener("click",t=>{t.target.matches("[data-confirm]")&&(t.preventDefault(),this.showConfirmationDialog(t.target))}),document.addEventListener("click",t=>{let e=t.target instanceof Element?t.target:t.target&&t.target.parentNode instanceof Element?t.target.parentNode:null,s=e==null?void 0:e.closest("[data-ajax-action]");s&&(t.preventDefault(),typeof t.stopImmediatePropagation=="function"?t.stopImmediatePropagation():t.stopPropagation(),this.handleAjaxAction(s))})}enhanceExistingElements(){document.querySelectorAll(".widefat").forEach(t=>{t.classList.contains("tts-enhanced-table")||t.classList.add("tts-enhanced-table")}),document.querySelectorAll(".button").forEach(t=>{t.classList.contains("tts-btn-enhanced")||t.classList.add("tts-btn-enhanced")}),this.addSearchToLists()}handleBulkSelectAll(t){let e=t.closest("form")||document,s=e.querySelectorAll(".tts-bulk-select-item"),a=e.querySelector(".tts-bulk-actions");s.forEach(o=>{o.checked=t.checked}),a&&a.classList.toggle("show",t.checked)}handleBulkSelectItem(){let t=event.target.closest("form")||document,e=t.querySelectorAll(".tts-bulk-select-item"),s=t.querySelector(".tts-bulk-select-all"),a=t.querySelector(".tts-bulk-actions"),o=Array.from(e).filter(n=>n.checked).length,r=e.length;s&&(s.checked=o===r,s.indeterminate=o>0&&o<r),a&&a.classList.toggle("show",o>0)}showConfirmationDialog(t){let e=t.getAttribute("data-confirm"),s=t.getAttribute("data-confirm-title")||"Confirm Action",a=t.getAttribute("data-confirm-button")||"Confirm",o=t.getAttribute("data-cancel-button")||"Cancel",r=t.hasAttribute("data-dangerous"),n=this.createModal({title:s,body:e,buttons:[{text:o,class:"button",onclick:()=>this.closeModal(n)},{text:a,class:r?"button-primary button-danger":"button-primary",onclick:()=>{this.closeModal(n),t.href?window.location.href=t.href:t.onclick?t.onclick():t.type==="submit"&&t.closest("form").submit()}}]});this.showModal(n)}async handleAjaxAction(t){let e=t.getAttribute("data-ajax-action"),s=this.getElementData(t),a=t.getAttribute("data-loading-text")||"Loading...",o=t.textContent;t.classList.add("tts-loading-btn"),t.textContent=a;try{let r=await this.ajaxRequest(e,s);if(r.success){let n=r.data||{},i=!1;if(n.modal_html&&(i=!!this.openModalFromHtml(n.modal_html)),i?n.message&&window.TTSNotifications&&window.TTSNotifications.success(n.message):window.TTSNotifications&&window.TTSNotifications.success(n.message||"Action completed successfully"),n.redirect){window.location.href=n.redirect;return}if(n.refresh){window.location.reload();return}let c=t.getAttribute("data-success-callback");c&&window[c]&&window[c](n)}else throw new Error(r.data||"Action failed")}catch(r){window.TTSNotifications.error(r.message||"An error occurred")}finally{t.classList.remove("tts-loading-btn"),t.textContent=o}}getElementData(t){var a,o,r,n;let e={};Array.from(t.attributes).forEach(i=>{if(i.name.startsWith("data-")&&i.name!=="data-ajax-action"){let c=i.name.replace("data-","").replace(/-([a-z])/g,h=>h[1].toUpperCase());e[c]=i.value}});let s=((a=window.tts_ajax)==null?void 0:a.nonce)||((n=(r=(o=window.TTS)==null?void 0:o.Core)==null?void 0:r.config)==null?void 0:n.nonce)||"";return s&&(!Object.hasOwn(e,"nonce")||!e.nonce)&&(e.nonce=s),e}async ajaxRequest(t,e={}){let s=new FormData;s.append("action",t),Object.keys(e).forEach(n=>{s.append(n,e[n])});let a=await fetch(window.ajaxurl||"/wp-admin/admin-ajax.php",{method:"POST",body:s,credentials:"same-origin"});if(!a.ok)throw new Error(`HTTP error! status: ${a.status}`);let r=(await a.text()).trim();if(!r)return{success:!1,data:"Empty response from server."};try{return JSON.parse(r)}catch(n){return r==="-1"||r==="0"?{success:!1,data:"Security check failed. Please refresh the page and try again."}:{success:!1,data:"The server returned an unexpected response."}}}openModalFromHtml(t){if(!t)return null;let e=document.createElement("div");e.innerHTML=t.trim();let s=e.querySelector("h1, h2, h3"),a="";s&&(a=s.textContent.trim(),s.remove());let o=this.createModal({title:a||"",body:e.innerHTML});if(!o)return null;this.showModal(o);let r=o.querySelector("input, select, textarea, button:not([disabled])");return r&&r.focus(),o}createModal({title:t,body:e,buttons:s=[]}){let a=document.createElement("div");a.className="tts-modal-overlay";let o=document.createElement("div");return o.className="tts-modal",o.innerHTML=`
            <div class="tts-modal-header">
                <h3 class="tts-modal-title">${t}</h3>
                <button class="tts-modal-close" type="button">\xD7</button>
            </div>
            <div class="tts-modal-body">
                ${e}
            </div>
            ${s.length>0?`
                <div class="tts-modal-footer">
                    ${s.map(r=>`
                        <button class="button ${r.class||""}" type="button">${r.text}</button>
                    `).join("")}
                </div>
            `:""}
        `,s.forEach((r,n)=>{let i=o.querySelectorAll(".tts-modal-footer button")[n];r.onclick&&(i.onclick=r.onclick)}),o.querySelectorAll(".tts-modal-close").forEach(r=>{r.addEventListener("click",n=>{n.preventDefault(),this.closeModal(a)})}),a.onclick=r=>{r.target===a&&this.closeModal(a)},a.appendChild(o),a}showModal(t){document.body.appendChild(t),requestAnimationFrame(()=>{t.classList.add("show"),t.querySelector(".tts-modal").classList.add("show")})}closeModal(t){t.classList.remove("show"),t.querySelector(".tts-modal").classList.remove("show"),setTimeout(()=>{t.parentNode&&t.parentNode.removeChild(t)},300)}addSearchToLists(){document.querySelectorAll(".tts-searchable-list").forEach(t=>{if(t.querySelector(".tts-search-container"))return;let e=document.createElement("div");e.className="tts-search-container",e.innerHTML=`
                <span class="tts-search-icon">\u{1F50D}</span>
                <input type="text" class="tts-search-input" placeholder="Search...">
            `,t.insertBefore(e,t.firstChild),e.querySelector(".tts-search-input").addEventListener("input",a=>{this.filterList(t,a.target.value)})})}filterList(t,e){let s=t.querySelectorAll(".tts-list-item, tr"),a=e.toLowerCase();s.forEach(o=>{let r=o.textContent.toLowerCase();o.style.display=r.includes(a)?"":"none"})}showProgress(t,e){let s=t.querySelector(".tts-progress");s||(s=document.createElement("div"),s.className="tts-progress",s.innerHTML='<div class="tts-progress-bar"></div>',t.appendChild(s));let a=s.querySelector(".tts-progress-bar");a.style.width=e+"%"}formatDate(t){return new Intl.DateTimeFormat("default",{year:"numeric",month:"short",day:"numeric",hour:"2-digit",minute:"2-digit"}).format(new Date(t))}debounce(t,e){let s;return function(...o){let r=()=>{clearTimeout(s),t(...o)};clearTimeout(s),s=setTimeout(r,e)}}throttle(t,e){let s;return function(){let a=arguments,o=this;s||(t.apply(o,a),s=!0,setTimeout(()=>s=!1,e))}}validateForm(t){let e=t.querySelectorAll("input[required], select[required], textarea[required]"),s=!0,a=null;return e.forEach(o=>{!this.validateField(o)&&s&&(s=!1,a=o)}),a&&(a.focus(),this.announceError("Please check the form for errors and try again.")),s}validateField(t){let e=t.value.trim(),s=t.type,a=t.hasAttribute("required"),o=!0,r="";this.clearFieldError(t),a&&!e&&(o=!1,r="This field is required."),s==="email"&&e&&!this.isValidEmail(e)&&(o=!1,r="Please enter a valid email address."),s==="url"&&e&&!this.isValidUrl(e)&&(o=!1,r="Please enter a valid URL.");let n=t.getAttribute("pattern");return n&&e&&!new RegExp(n).test(e)&&(o=!1,r=t.getAttribute("data-pattern-error")||"Invalid format."),o?this.showFieldSuccess(t):this.showFieldError(t,r),o}showFieldError(t,e){t.classList.add("tts-field-error"),t.setAttribute("aria-invalid","true");let s=t.nextElementSibling;(!s||!s.classList.contains("tts-error-message"))&&(s=document.createElement("div"),s.className="tts-error-message",s.setAttribute("role","alert"),t.parentNode.insertBefore(s,t.nextSibling)),s.textContent=e}showFieldSuccess(t){t.classList.add("tts-field-success"),t.setAttribute("aria-invalid","false")}clearFieldError(t){t.classList.remove("tts-field-error","tts-field-success"),t.removeAttribute("aria-invalid");let e=t.nextElementSibling;e&&e.classList.contains("tts-error-message")&&e.remove()}isValidEmail(t){return/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t)}isValidUrl(t){try{return new URL(t),!0}catch(e){return!1}}announceError(t){let e=document.createElement("div");e.setAttribute("aria-live","assertive"),e.setAttribute("aria-atomic","true"),e.style.position="absolute",e.style.left="-10000px",e.style.width="1px",e.style.height="1px",e.style.overflow="hidden",e.textContent=t,document.body.appendChild(e),setTimeout(()=>document.body.removeChild(e),1e3)}};window.TTSAdminUtils=new d;typeof l!="undefined"&&l.exports&&(l.exports=d)});p();})();
//# sourceMappingURL=tts-admin-utils-ILDNZHJV.js.map
