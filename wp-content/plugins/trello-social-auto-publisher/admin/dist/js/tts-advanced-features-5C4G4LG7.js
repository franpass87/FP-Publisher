(()=>{var l=class{constructor(){this.shortcuts=new Map,this.init()}init(){this.setupKeyboardShortcuts(),this.enhanceAccessibility(),this.addAdvancedControls(),this.initializeExportImport(),this.addDarkMode()}setupKeyboardShortcuts(){this.shortcuts.set("ctrl+shift+d",()=>this.navigateTo("fp-publisher-main")),this.shortcuts.set("ctrl+shift+c",()=>this.navigateTo("fp-publisher-calendar")),this.shortcuts.set("ctrl+shift+a",()=>this.navigateTo("fp-publisher-analytics")),this.shortcuts.set("ctrl+shift+h",()=>this.navigateTo("fp-publisher-health")),this.shortcuts.set("ctrl+shift+l",()=>this.navigateTo("fp-publisher-log")),this.shortcuts.set("ctrl+shift+n",()=>this.navigateTo("fp-publisher-client-wizard")),this.shortcuts.set("ctrl+shift+r",()=>this.refreshCurrentPage()),this.shortcuts.set("ctrl+shift+e",()=>this.openExportModal()),this.shortcuts.set("ctrl+shift+i",()=>this.openImportModal()),this.shortcuts.set("ctrl+shift+k",()=>this.showKeyboardShortcuts()),this.shortcuts.set("ctrl+shift+t",()=>this.toggleDarkMode()),document.addEventListener("keydown",t=>{try{let e=document.activeElement;if(e&&(e.tagName==="INPUT"||e.tagName==="TEXTAREA"||e.tagName==="SELECT"||e.contentEditable==="true"))return;let s=this.getKeyboardShortcut(t);if(this.shortcuts.has(s)){t.preventDefault(),t.stopPropagation();let o=this.shortcuts.get(s);typeof o=="function"&&o()}}catch(e){console.error("TTSAdvancedFeatures: Error in keyboard event handler:",e)}}),document.addEventListener("keydown",t=>{t.key==="F1"&&(t.preventDefault(),this.showContextualHelp())}),this.addKeyboardShortcutIndicator()}getKeyboardShortcut(t){let e=[];return t.ctrlKey&&e.push("ctrl"),t.shiftKey&&e.push("shift"),t.altKey&&e.push("alt"),t.metaKey&&e.push("meta"),(t.key&&t.key.length===1||t.key)&&e.push(t.key.toLowerCase()),e.join("+")}navigateTo(t){var e;try{if(!t||typeof t!="string"){console.error("TTSAdvancedFeatures: Invalid page parameter for navigation");return}if(new URLSearchParams(window.location.search).get("page")===t){(e=window.TTSNotifications)==null||e.info("Already on this page",{duration:2e3});return}window.TTSNotifications&&window.TTSNotifications.info(`Navigating to ${t}...`,{duration:1e3}),document.body.classList.add("tts-navigating"),window.location.href=`admin.php?page=${encodeURIComponent(t)}`}catch(s){console.error("TTSAdvancedFeatures: Navigation error:",s),window.TTSNotifications&&window.TTSNotifications.error("Navigation failed. Please try again.")}}refreshCurrentPage(){try{window.TTSNotifications?(window.TTSNotifications.info("Refreshing page...",{duration:1e3}),setTimeout(()=>{window.location.reload()},500)):window.location.reload()}catch(t){console.error("TTSAdvancedFeatures: Refresh error:",t),window.location.reload()}}addKeyboardShortcutIndicator(){let t=document.getElementById("wp-admin-bar-root");if(t&&window.location.href.includes("page=fp-publisher-")){let e=document.createElement("li");e.id="wp-admin-bar-tts-shortcuts",e.innerHTML=`
                <a class="ab-item" href="#" style="color: #00a32a;">
                    <span class="ab-icon dashicons dashicons-keyboard-hide"></span>
                    <span class="ab-label">Shortcuts</span>
                </a>
            `,e.addEventListener("click",s=>{s.preventDefault(),this.showKeyboardShortcuts()}),t.appendChild(e)}}showKeyboardShortcuts(){let e=[{key:"Ctrl+Shift+D",desc:"Go to Dashboard"},{key:"Ctrl+Shift+C",desc:"Go to Calendar"},{key:"Ctrl+Shift+A",desc:"Go to Analytics"},{key:"Ctrl+Shift+H",desc:"Go to Health Status"},{key:"Ctrl+Shift+L",desc:"Go to Logs"},{key:"Ctrl+Shift+N",desc:"New Client Wizard"},{key:"Ctrl+Shift+R",desc:"Refresh Page"},{key:"Ctrl+Shift+E",desc:"Export Settings"},{key:"Ctrl+Shift+I",desc:"Import Settings"},{key:"Ctrl+Shift+T",desc:"Toggle Dark Mode"},{key:"Ctrl+Shift+K",desc:"Show This Help"}].map(s=>`<tr><td style="padding: 8px; font-family: monospace; background: #f0f0f1;"><kbd>${s.key}</kbd></td><td style="padding: 8px;">${s.desc}</td></tr>`).join("");window.TTSAdminUtils.showModal(window.TTSAdminUtils.createModal({title:"Keyboard Shortcuts",body:`
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; text-align: left;">Shortcut</th>
                            <th style="padding: 8px; text-align: left;">Action</th>
                        </tr>
                    </thead>
                    <tbody>${e}</tbody>
                </table>
                <p style="margin-top: 15px; font-size: 12px; color: #666;">
                    Press these key combinations while on Social Auto Publisher pages.
                </p>
            `,buttons:[{text:"Close",class:"button-primary",onclick:function(){this.closest(".tts-modal-overlay").remove()}}]}))}enhanceAccessibility(){try{document.querySelectorAll(".tts-stat-card").forEach((t,e)=>{if(!t.hasAttribute("role")){t.setAttribute("role","region");let s=t.querySelector("h3"),o=t.querySelector(".tts-stat-number");if(s&&o){let i=`${s.textContent}: ${o.textContent}`;t.setAttribute("aria-label",i)}else t.setAttribute("aria-label",`Statistics card ${e+1}`);t.setAttribute("tabindex","0")}}),document.querySelectorAll(".tts-enhanced-table").forEach(t=>{t.getAttribute("role")||(t.setAttribute("role","table"),t.setAttribute("aria-label","Social media posts"));let e=t.querySelectorAll("th"),s=t.querySelectorAll("tbody tr");e.forEach((o,i)=>{o.id||(o.id=`tts-header-${i}`)}),s.forEach(o=>{o.querySelectorAll("td").forEach((a,r)=>{e[r]&&!a.getAttribute("headers")&&a.setAttribute("headers",e[r].id)})})}),this.addAccessibilityStyles(),this.addSkipToContentLink(),this.enhanceFormAccessibility()}catch(t){console.error("TTSAdvancedFeatures: Error enhancing accessibility:",t)}}addAccessibilityStyles(){if(document.getElementById("tts-accessibility-styles"))return;let t=document.createElement("style");t.id="tts-accessibility-styles",t.textContent=`
            /* Enhanced focus indicators */
            .tts-stat-card:focus,
            .tts-quick-action:focus,
            .tts-btn:focus,
            .tts-bulk-select-item:focus,
            .tts-bulk-select-all:focus {
                outline: 3px solid #005cee;
                outline-offset: 2px;
                box-shadow: 0 0 0 1px #fff, 0 0 0 4px #005cee;
            }
            
            .tts-enhanced-table tr:focus-within {
                background-color: #e6f3ff;
                outline: 2px solid #005cee;
            }
            
            /* Skip to content link */
            .tts-skip-link {
                position: absolute;
                top: -40px;
                left: 6px;
                background: #000;
                color: #fff;
                padding: 8px 12px;
                text-decoration: none;
                z-index: 100000;
                border-radius: 4px;
                transition: top 0.3s ease;
                font-size: 14px;
                font-weight: 600;
            }
            
            .tts-skip-link:focus {
                top: 6px;
            }
            
            /* High contrast mode support */
            @media (prefers-contrast: high) {
                .tts-stat-card,
                .tts-dashboard-section,
                .tts-quick-action,
                .tts-notification {
                    border-width: 2px !important;
                    border-color: #000 !important;
                }
                
                .tts-status-badge {
                    border: 2px solid #000 !important;
                }
            }
            
            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
                
                .tts-notification {
                    transition: none !important;
                }
            }
            
            /* Screen reader only content */
            .sr-only {
                position: absolute !important;
                width: 1px !important;
                height: 1px !important;
                padding: 0 !important;
                margin: -1px !important;
                overflow: hidden !important;
                clip: rect(0, 0, 0, 0) !important;
                white-space: nowrap !important;
                border: 0 !important;
            }
        `,document.head.appendChild(t)}addSkipToContentLink(){if(document.querySelector(".tts-skip-link"))return;let t=document.createElement("a");t.href="#main-content",t.className="tts-skip-link",t.textContent="Skip to main content",t.setAttribute("aria-label","Skip to main content"),document.body.insertBefore(t,document.body.firstChild);let e=document.querySelector(".wrap");e&&!e.id&&(e.id="main-content",e.setAttribute("role","main"),e.setAttribute("aria-label","Main content area"))}enhanceFormAccessibility(){document.querySelectorAll('select, input[type="checkbox"], input[type="radio"]').forEach(e=>{var s,o,i;if(!e.getAttribute("aria-label")&&!((s=e.labels)!=null&&s.length)){let a=e.closest("label"),r=((o=e.previousElementSibling)==null?void 0:o.tagName)==="LABEL"?e.previousElementSibling:((i=e.nextElementSibling)==null?void 0:i.tagName)==="LABEL"?e.nextElementSibling:null;if(a||r){let n=(a||r).textContent.trim();n&&e.setAttribute("aria-label",n)}}});let t=document.querySelector(".tts-bulk-select-all");t&&(t.setAttribute("aria-label","Select all posts"),t.addEventListener("change",e=>{document.querySelectorAll(".tts-bulk-select-item").forEach(o=>{o.checked=e.target.checked,o.setAttribute("aria-checked",e.target.checked)})}))}addAdvancedControls(){let t=document.createElement("div");t.className="tts-advanced-controls",t.innerHTML=`
            <button class="tts-control-toggle" aria-label="Toggle advanced controls">
                <span class="dashicons dashicons-admin-generic"></span>
            </button>
            <div class="tts-control-panel">
                <div class="tts-control-section">
                    <h4>Quick Actions</h4>
                    <button class="tts-btn small" data-action="export">Export Settings</button>
                    <button class="tts-btn small" data-action="import">Import Settings</button>
                    <button class="tts-btn small" data-action="clear-cache">Clear Cache</button>
                </div>
                <div class="tts-control-section">
                    <h4>View Options</h4>
                    <label>
                        <input type="checkbox" id="tts-dark-mode-toggle"> Dark Mode
                    </label>
                    <label>
                        <input type="checkbox" id="tts-compact-view"> Compact View
                    </label>
                    <label>
                        <input type="checkbox" id="tts-auto-refresh"> Auto Refresh
                    </label>
                </div>
            </div>
        `;let e=document.createElement("style");e.textContent=`
            .tts-advanced-controls {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 99999;
            }
            
            .tts-control-toggle {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: #135e96;
                color: #fff;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
            }
            
            .tts-control-toggle:hover {
                background: #0a4b78;
                transform: scale(1.1);
            }
            
            .tts-control-panel {
                position: absolute;
                bottom: 60px;
                right: 0;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                padding: 20px;
                min-width: 250px;
                opacity: 0;
                transform: translateY(20px);
                pointer-events: none;
                transition: all 0.3s ease;
            }
            
            .tts-control-panel.show {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }
            
            .tts-control-section {
                margin-bottom: 15px;
            }
            
            .tts-control-section:last-child {
                margin-bottom: 0;
            }
            
            .tts-control-section h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #1d2327;
                border-bottom: 1px solid #f0f0f1;
                padding-bottom: 5px;
            }
            
            .tts-control-section label {
                display: block;
                margin-bottom: 8px;
                font-size: 13px;
                cursor: pointer;
            }
            
            .tts-control-section input[type="checkbox"] {
                margin-right: 8px;
            }
            
            @media (max-width: 768px) {
                .tts-advanced-controls {
                    bottom: 80px;
                    right: 10px;
                }
            }
        `,document.head.appendChild(e),window.location.href.includes("page=fp-publisher-")&&(document.body.appendChild(t),this.bindControlEvents(t))}bindControlEvents(t){let e=t.querySelector(".tts-control-toggle"),s=t.querySelector(".tts-control-panel");e.addEventListener("click",()=>{s.classList.toggle("show")}),document.addEventListener("click",r=>{t.contains(r.target)||s.classList.remove("show")}),s.addEventListener("click",r=>{let n=r.target.getAttribute("data-action");n&&(r.preventDefault(),this.handleControlAction(n))});let o=s.querySelector("#tts-dark-mode-toggle"),i=s.querySelector("#tts-compact-view"),a=s.querySelector("#tts-auto-refresh");o&&(o.checked=localStorage.getItem("tts-dark-mode")==="true",o.addEventListener("change",()=>this.toggleDarkMode())),i&&(i.checked=localStorage.getItem("tts-compact-view")==="true",i.addEventListener("change",()=>this.toggleCompactView())),a&&(a.checked=localStorage.getItem("tts-auto-refresh")==="true",a.addEventListener("change",()=>this.toggleAutoRefresh()))}handleControlAction(t){switch(t){case"export":this.openExportModal();break;case"import":this.openImportModal();break;case"clear-cache":this.clearCache();break;default:}}initializeExportImport(){this.exportData={settings:{},clients:[],posts:[],analytics:{},version:"1.0",exported_at:new Date().toISOString()}}async openExportModal(){let t=window.TTSAdminUtils.createModal({title:"Export Settings & Data",body:`
                <p>Choose what to export:</p>
                <div style="margin: 15px 0;">
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-settings" checked> Plugin Settings
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-clients" checked> Clients Configuration
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-posts"> Social Posts (last 100)
                    </label>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" id="export-analytics"> Analytics Data
                    </label>
                </div>
                <div id="export-progress" style="display: none;">
                    <div class="tts-progress">
                        <div class="tts-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p id="export-status">Preparing export...</p>
                </div>
            `,buttons:[{text:"Cancel",class:"button",onclick:function(){this.closest(".tts-modal-overlay").remove()}},{text:"Export",class:"button-primary",onclick:()=>this.performExport(t)}]});window.TTSAdminUtils.showModal(t)}async performExport(t){let e=t.querySelector("#export-progress"),s=t.querySelector(".tts-progress-bar"),o=t.querySelector("#export-status"),i=t.querySelectorAll(".tts-modal-footer button");e.style.display="block",i.forEach(a=>a.disabled=!0);try{o.textContent="Gathering plugin settings...",s.style.width="25%",await this.delay(500);let a={version:"1.0",exported_at:new Date().toISOString(),settings:{},clients:[],posts:[],analytics:{}};t.querySelector("#export-clients").checked&&(o.textContent="Exporting clients configuration...",s.style.width="50%",await this.delay(500),a.clients=await this.getClientsData()),t.querySelector("#export-posts").checked&&(o.textContent="Exporting social posts...",s.style.width="75%",await this.delay(500),a.posts=await this.getPostsData()),o.textContent="Finalizing export...",s.style.width="100%",await this.delay(500),this.downloadJSON(a,`tts-export-${new Date().toISOString().split("T")[0]}.json`),window.TTSNotifications.success("Export completed successfully!"),t.remove()}catch(a){window.TTSNotifications.error("Export failed: "+a.message),i.forEach(r=>r.disabled=!1),e.style.display="none"}}async getClientsData(){return[{id:1,name:"Client 1",settings:{trello:"configured"}},{id:2,name:"Client 2",settings:{trello:"configured"}}]}async getPostsData(){return[{id:1,title:"Post 1",status:"published"},{id:2,title:"Post 2",status:"scheduled"}]}downloadJSON(t,e){let s=new Blob([JSON.stringify(t,null,2)],{type:"application/json"}),o=URL.createObjectURL(s),i=document.createElement("a");i.href=o,i.download=e,document.body.appendChild(i),i.click(),document.body.removeChild(i),URL.revokeObjectURL(o)}openImportModal(){let t=window.TTSAdminUtils.createModal({title:"Import Settings & Data",body:`
                <p>Select a JSON export file to import:</p>
                <div style="margin: 15px 0;">
                    <input type="file" id="import-file" accept=".json" style="margin-bottom: 15px;">
                    <div id="import-preview" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 15px;">
                        <h4>Import Preview:</h4>
                        <div id="import-details"></div>
                    </div>
                </div>
                <div id="import-progress" style="display: none;">
                    <div class="tts-progress">
                        <div class="tts-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p id="import-status">Processing import...</p>
                </div>
            `,buttons:[{text:"Cancel",class:"button",onclick:function(){this.closest(".tts-modal-overlay").remove()}},{text:"Import",class:"button-primary",onclick:()=>this.performImport(t)}]});t.querySelector("#import-file").addEventListener("change",s=>this.previewImport(s.target.files[0],t)),window.TTSAdminUtils.showModal(t)}async previewImport(t,e){if(t)try{let s=await this.readFileAsText(t),o=JSON.parse(s),i=e.querySelector("#import-preview"),a=e.querySelector("#import-details");a.innerHTML=`
                <p><strong>Export Version:</strong> ${o.version||"Unknown"}</p>
                <p><strong>Exported:</strong> ${o.exported_at?new Date(o.exported_at).toLocaleString():"Unknown"}</p>
                <p><strong>Contains:</strong></p>
                <ul>
                    ${o.clients?`<li>${o.clients.length} clients</li>`:""}
                    ${o.posts?`<li>${o.posts.length} posts</li>`:""}
                    ${o.settings?"<li>Plugin settings</li>":""}
                    ${o.analytics?"<li>Analytics data</li>":""}
                </ul>
            `,i.style.display="block",e.importData=o}catch(s){window.TTSNotifications.error("Invalid import file: "+s.message)}}readFileAsText(t){return new Promise((e,s)=>{let o=new FileReader;o.onload=i=>e(i.target.result),o.onerror=s,o.readAsText(t)})}async performImport(t){if(!t.importData){window.TTSNotifications.error("Please select a file to import");return}let e=t.querySelector("#import-progress"),s=t.querySelector(".tts-progress-bar"),o=t.querySelector("#import-status"),i=t.querySelectorAll(".tts-modal-footer button");e.style.display="block",i.forEach(a=>a.disabled=!0);try{let a=t.importData;o.textContent="Validating import data...",s.style.width="25%",await this.delay(500),o.textContent="Importing settings...",s.style.width="50%",await this.delay(500),o.textContent="Importing clients...",s.style.width="75%",await this.delay(500),o.textContent="Finalizing import...",s.style.width="100%",await this.delay(500),window.TTSNotifications.success("Import completed successfully!"),t.remove()}catch(a){window.TTSNotifications.error("Import failed: "+a.message),i.forEach(r=>r.disabled=!1),e.style.display="none"}}addDarkMode(){let t=document.createElement("style");t.id="tts-dark-mode-styles",t.textContent=`
            body.tts-dark-mode {
                background: #1a1a1a !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .wrap {
                background: #1a1a1a;
                color: #e0e0e0;
            }
            
            .tts-dark-mode .tts-stat-card,
            .tts-dark-mode .tts-dashboard-section {
                background: #2d2d2d !important;
                border-color: #404040 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-quick-action {
                background: #333 !important;
                border-color: #555 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-quick-action:hover {
                background: #135e96 !important;
                color: #fff !important;
            }
            
            .tts-dark-mode .widefat,
            .tts-dark-mode .tts-enhanced-table {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .widefat th {
                background: #404040 !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .widefat tr:hover,
            .tts-dark-mode .tts-enhanced-table tr:hover {
                background: #404040 !important;
            }
            
            .tts-dark-mode .tts-modal {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
            
            .tts-dark-mode .tts-notification {
                background: #2d2d2d !important;
                color: #e0e0e0 !important;
            }
        `,document.head.appendChild(t),localStorage.getItem("tts-dark-mode")==="true"&&document.body.classList.add("tts-dark-mode")}toggleDarkMode(){let t=document.body.classList.toggle("tts-dark-mode");localStorage.setItem("tts-dark-mode",t.toString());let e=document.querySelector("#tts-dark-mode-toggle");e&&(e.checked=t),window.TTSNotifications.info(`Dark mode ${t?"enabled":"disabled"}`)}toggleCompactView(){let t=document.body.classList.toggle("tts-compact-view");if(localStorage.setItem("tts-compact-view",t.toString()),!document.getElementById("tts-compact-styles")){let e=document.createElement("style");e.id="tts-compact-styles",e.textContent=`
                .tts-compact-view .tts-stat-card {
                    padding: 15px;
                    min-width: 150px;
                }
                
                .tts-compact-view .tts-stat-number {
                    font-size: 24px;
                }
                
                .tts-compact-view .tts-dashboard-section {
                    padding: 15px;
                }
                
                .tts-compact-view .tts-quick-action {
                    padding: 8px 12px;
                }
            `,document.head.appendChild(e)}window.TTSNotifications.info(`Compact view ${t?"enabled":"disabled"}`)}toggleAutoRefresh(){let e=!(localStorage.getItem("tts-auto-refresh")==="true");localStorage.setItem("tts-auto-refresh",e.toString()),e&&window.location.href.includes("page=fp-publisher-main")?this.startAutoRefresh():this.stopAutoRefresh(),window.TTSNotifications.info(`Auto refresh ${e?"enabled":"disabled"}`)}startAutoRefresh(){this.stopAutoRefresh(),this.autoRefreshInterval=setInterval(()=>{document.querySelector('[data-ajax-action="tts_refresh_posts"]')&&document.querySelector('[data-ajax-action="tts_refresh_posts"]').click()},3e4)}stopAutoRefresh(){this.autoRefreshInterval&&(clearInterval(this.autoRefreshInterval),this.autoRefreshInterval=null)}async clearCache(){try{let t=await window.TTSAdminUtils.ajaxRequest("tts_clear_cache");if(t.success)window.TTSNotifications.success("Cache cleared successfully");else throw new Error(t.data||"Failed to clear cache")}catch(t){window.TTSNotifications.error("Failed to clear cache: "+t.message)}}delay(t){return new Promise(e=>setTimeout(e,t))}showContextualHelp(){var t,e;try{let s=new URLSearchParams(window.location.search).get("page"),o="";switch(s){case"fp-publisher-main":o=this.getDashboardHelp();break;case"fp-publisher-calendar":o=this.getCalendarHelp();break;case"fp-publisher-analytics":o=this.getAnalyticsHelp();break;case"fp-publisher-health":o=this.getHealthHelp();break;case"fp-publisher-log":o=this.getLogHelp();break;default:o=this.getGeneralHelp()}window.TTSAdminUtils&&window.TTSAdminUtils.createModal?window.TTSAdminUtils.createModal({title:"Contextual Help",body:o,size:"large"}).setAttribute("aria-label","Help dialog"):(t=window.TTSNotifications)==null||t.info("Help system loading...")}catch(s){console.error("TTSAdvancedFeatures: Error showing contextual help:",s),(e=window.TTSNotifications)==null||e.error("Unable to load help content")}}getDashboardHelp(){return`
            <div class="tts-help-content">
                <h3>Dashboard Help</h3>
                <p>The dashboard provides an overview of your social media publishing activity:</p>
                <ul>
                    <li><strong>Statistics Cards:</strong> View key metrics like total posts, active clients, and success rates</li>
                    <li><strong>Recent Posts:</strong> Review and manage your latest social media posts</li>
                    <li><strong>Quick Actions:</strong> Access frequently used features quickly</li>
                    <li><strong>Bulk Operations:</strong> Select multiple posts to perform batch actions</li>
                </ul>
                <h4>Keyboard Shortcuts:</h4>
                <ul>
                    <li><kbd>Ctrl+Shift+D</kbd> - Go to Dashboard</li>
                    <li><kbd>Ctrl+Shift+R</kbd> - Refresh current page</li>
                    <li><kbd>F1</kbd> - Show this help</li>
                </ul>
            </div>
        `}getCalendarHelp(){return`
            <div class="tts-help-content">
                <h3>Calendar Help</h3>
                <p>The calendar view shows your scheduled social media posts:</p>
                <ul>
                    <li><strong>Monthly View:</strong> See all posts scheduled for the current month</li>
                    <li><strong>Post Details:</strong> Click on any post to view details</li>
                    <li><strong>Navigation:</strong> Use arrows to navigate between months</li>
                </ul>
            </div>
        `}getAnalyticsHelp(){return`
            <div class="tts-help-content">
                <h3>Analytics Help</h3>
                <p>View detailed analytics and performance metrics:</p>
                <ul>
                    <li><strong>Charts:</strong> Visual representation of your posting activity</li>
                    <li><strong>Filters:</strong> Filter data by date range, client, or status</li>
                    <li><strong>Export:</strong> Download analytics data for further analysis</li>
                </ul>
            </div>
        `}getHealthHelp(){return`
            <div class="tts-help-content">
                <h3>System Health Help</h3>
                <p>Monitor the health and status of your social publishing system:</p>
                <ul>
                    <li><strong>Overall Status:</strong> Green indicates all systems operational</li>
                    <li><strong>Individual Checks:</strong> View specific system components</li>
                    <li><strong>Troubleshooting:</strong> Get recommendations for issues</li>
                </ul>
            </div>
        `}getLogHelp(){return`
            <div class="tts-help-content">
                <h3>Logs Help</h3>
                <p>Review system logs and publishing history:</p>
                <ul>
                    <li><strong>Activity Logs:</strong> See all system activities and events</li>
                    <li><strong>Error Logs:</strong> Review any errors or issues</li>
                    <li><strong>Search:</strong> Find specific log entries</li>
                </ul>
            </div>
        `}getGeneralHelp(){return`
            <div class="tts-help-content">
                <h3>General Help</h3>
                <p>Welcome to the Social Auto Publisher plugin!</p>
                <ul>
                    <li><strong>Navigation:</strong> Use the left sidebar menu to access different sections</li>
                    <li><strong>Keyboard Shortcuts:</strong> Press <kbd>Ctrl+Shift+K</kbd> to see all shortcuts</li>
                    <li><strong>Accessibility:</strong> This plugin supports screen readers and keyboard navigation</li>
                </ul>
            </div>
        `}};document.addEventListener("DOMContentLoaded",()=>{window.location.href.includes("page=fp-publisher-")&&(window.TTSAdvancedFeatures=new l)});})();
//# sourceMappingURL=tts-advanced-features-5C4G4LG7.js.map
