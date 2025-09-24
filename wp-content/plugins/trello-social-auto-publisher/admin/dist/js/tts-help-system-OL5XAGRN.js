(()=>{var a=class{constructor(){this.helpData={},this.currentTour=null,this.init()}init(){this.loadHelpData(),this.addHelpButton(),this.bindHelpEvents()}loadHelpData(){this.helpData={"fp-publisher-main":{title:"Dashboard Help",sections:[{title:"Statistics Cards",content:"The dashboard shows key metrics including total posts, active clients, scheduled posts, and today's published content. Hover over cards for detailed tooltips.",selector:".tts-stats-row"},{title:"Recent Posts",content:"View and manage your latest social media posts. Use the refresh button to update the list, select multiple posts for bulk actions.",selector:".tts-dashboard-left"},{title:"Quick Actions",content:"Access frequently used features quickly. Click any action to navigate to the respective page.",selector:".tts-quick-actions"},{title:"System Status",content:"Monitor the health of your Social Auto Publisher system. Green indicators show everything is working correctly.",selector:".tts-dashboard-right"}],shortcuts:["Ctrl+Shift+R: Refresh data","Ctrl+Shift+E: Export settings","Ctrl+Shift+K: Show keyboard shortcuts"]},"fp-publisher-calendar":{title:"Calendar Help",sections:[{title:"Monthly View",content:"View scheduled posts organized by date. Each post shows the time and associated social media channels.",selector:".calendar-container"},{title:"Navigation",content:"Use the previous/next buttons to navigate between months. The current month is highlighted.",selector:".calendar-nav"}],shortcuts:["Ctrl+Shift+C: Go to calendar","Left/Right arrows: Navigate months"]},"fp-publisher-analytics":{title:"Analytics Help",sections:[{title:"Performance Metrics",content:"Track engagement, reach, and performance across all your social media channels.",selector:".analytics-summary"},{title:"Filters",content:"Filter data by date range, social media channel, or client to get specific insights.",selector:".analytics-filters"}],shortcuts:["Ctrl+Shift+A: Go to analytics","Ctrl+Shift+E: Export analytics"]},"fp-publisher-health":{title:"Health Status Help",sections:[{title:"System Health",content:"Monitor the overall health of your Social Auto Publisher installation and connected services.",selector:".health-overview"},{title:"Token Validation",content:"Check the status of your social media API tokens. Red indicators require attention.",selector:".token-status"}],shortcuts:["Ctrl+Shift+H: Go to health status"]}}}addHelpButton(){let t=document.getElementById("wp-admin-bar-root");if(t&&window.location.href.includes("page=fp-publisher-")){let s=document.createElement("li");s.id="wp-admin-bar-tts-help",s.innerHTML=`
                <a class="ab-item" href="#" style="color: #00a32a;">
                    <span class="ab-icon dashicons dashicons-editor-help"></span>
                    <span class="ab-label">Help</span>
                </a>
            `,s.addEventListener("click",i=>{i.preventDefault(),this.showHelpModal()}),t.appendChild(s)}let e=document.createElement("div");e.className="tts-floating-help",e.innerHTML=`
            <button class="tts-help-toggle" title="Get Help (F1)">
                <span class="dashicons dashicons-editor-help"></span>
            </button>
            <div class="tts-help-menu">
                <button data-action="contextual-help">Contextual Help</button>
                <button data-action="start-tour">Start Tour</button>
                <button data-action="keyboard-shortcuts">Shortcuts</button>
                <button data-action="documentation">Documentation</button>
            </div>
        `;let o=document.createElement("style");o.textContent=`
            .tts-floating-help {
                position: fixed;
                bottom: 80px;
                left: 20px;
                z-index: 99999;
            }
            
            .tts-help-toggle {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                background: #00a32a;
                color: #fff;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transition: all 0.3s ease;
            }
            
            .tts-help-toggle:hover {
                background: #008a20;
                transform: scale(1.1);
            }
            
            .tts-help-menu {
                position: absolute;
                bottom: 55px;
                left: 0;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                padding: 10px 0;
                min-width: 180px;
                opacity: 0;
                transform: translateY(20px);
                pointer-events: none;
                transition: all 0.3s ease;
            }
            
            .tts-help-menu.show {
                opacity: 1;
                transform: translateY(0);
                pointer-events: auto;
            }
            
            .tts-help-menu button {
                display: block;
                width: 100%;
                padding: 10px 15px;
                border: none;
                background: none;
                text-align: left;
                cursor: pointer;
                transition: background 0.2s;
                font-size: 13px;
            }
            
            .tts-help-menu button:hover {
                background: #f0f0f1;
            }
            
            .tts-tour-highlight {
                position: relative;
                z-index: 10000;
                box-shadow: 0 0 0 4px #00a32a, 0 0 0 8px rgba(0,163,42,0.3);
                border-radius: 4px;
                animation: tts-pulse-highlight 2s infinite;
            }
            
            @keyframes tts-pulse-highlight {
                0%, 100% { box-shadow: 0 0 0 4px #00a32a, 0 0 0 8px rgba(0,163,42,0.3); }
                50% { box-shadow: 0 0 0 4px #00a32a, 0 0 0 12px rgba(0,163,42,0.1); }
            }
            
            .tts-tour-tooltip {
                position: absolute;
                background: #333;
                color: #fff;
                padding: 15px;
                border-radius: 8px;
                max-width: 300px;
                z-index: 10001;
                font-size: 14px;
                line-height: 1.4;
            }
            
            .tts-tour-tooltip::after {
                content: '';
                position: absolute;
                width: 0;
                height: 0;
                border: 8px solid transparent;
            }
            
            .tts-tour-tooltip.bottom::after {
                top: -16px;
                left: 20px;
                border-bottom-color: #333;
            }
            
            .tts-tour-tooltip.top::after {
                bottom: -16px;
                left: 20px;
                border-top-color: #333;
            }
            
            .tts-tour-controls {
                margin-top: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .tts-tour-progress {
                font-size: 12px;
                color: #ccc;
            }
            
            .tts-tour-buttons {
                display: flex;
                gap: 8px;
            }
            
            .tts-tour-btn {
                background: #00a32a;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: background 0.2s;
            }
            
            .tts-tour-btn:hover {
                background: #008a20;
            }
            
            .tts-tour-btn.secondary {
                background: #666;
            }
            
            .tts-tour-btn.secondary:hover {
                background: #555;
            }
            
            @media (max-width: 768px) {
                .tts-floating-help {
                    bottom: 120px;
                    left: 10px;
                }
                
                .tts-tour-tooltip {
                    max-width: 250px;
                    padding: 12px;
                }
            }
        `,document.head.appendChild(o),window.location.href.includes("page=fp-publisher-")&&(document.body.appendChild(e),this.bindFloatingHelpEvents(e))}bindFloatingHelpEvents(t){let e=t.querySelector(".tts-help-toggle"),o=t.querySelector(".tts-help-menu");e.addEventListener("click",()=>{o.classList.toggle("show")}),document.addEventListener("click",s=>{t.contains(s.target)||o.classList.remove("show")}),o.addEventListener("click",s=>{let i=s.target.getAttribute("data-action");i&&(s.preventDefault(),this.handleHelpAction(i),o.classList.remove("show"))})}bindHelpEvents(){document.addEventListener("keydown",t=>{t.key==="F1"&&(t.preventDefault(),this.showContextualHelp())}),this.addHelpHints()}addHelpHints(){document.querySelectorAll(".tts-stat-card").forEach((t,e)=>{t.setAttribute("data-help",`stat-card-${e}`)}),document.querySelectorAll(".tts-quick-action").forEach((t,e)=>{t.setAttribute("data-help",`quick-action-${e}`)})}handleHelpAction(t){switch(t){case"contextual-help":this.showContextualHelp();break;case"start-tour":this.startGuidedTour();break;case"keyboard-shortcuts":window.TTSAdvancedFeatures&&window.TTSAdvancedFeatures.showKeyboardShortcuts();break;case"documentation":this.showDocumentation();break}}showHelpModal(){let t=this.getCurrentPage(),e=this.helpData[t];if(!e){this.showGenericHelp();return}let o=e.sections.map(n=>`
            <div class="help-section" style="margin-bottom: 20px;">
                <h4 style="margin: 0 0 8px 0; color: #135e96;">${n.title}</h4>
                <p style="margin: 0 0 10px 0; line-height: 1.5;">${n.content}</p>
                ${n.selector?`<button class="tts-btn small" onclick="TTSHelpSystem.instance.highlightElement('${n.selector}')">Show Me</button>`:""}
            </div>
        `).join(""),s=e.shortcuts?`
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f0f1;">
                <h4 style="margin: 0 0 10px 0;">Keyboard Shortcuts</h4>
                <ul style="margin: 0; padding-left: 20px;">
                    ${e.shortcuts.map(n=>`<li style="margin-bottom: 5px; font-family: monospace; font-size: 12px;">${n}</li>`).join("")}
                </ul>
            </div>
        `:"",i=window.TTSAdminUtils.createModal({title:e.title,body:`
                <div class="tts-help-content">
                    ${o}
                    ${s}
                </div>
            `,buttons:[{text:"Start Tour",class:"button",onclick:()=>{i.remove(),this.startGuidedTour()}},{text:"Close",class:"button-primary",onclick:function(){this.closest(".tts-modal-overlay").remove()}}]});window.TTSAdminUtils.showModal(i)}showContextualHelp(){let t=this.getCurrentPage();this.helpData[t]?this.showHelpModal():window.TTSNotifications.info("Help available: Press F1 for contextual help, or use the help button for a guided tour.")}startGuidedTour(){let t=this.getCurrentPage(),e=this.helpData[t];if(!e||!e.sections){window.TTSNotifications.warning("No guided tour available for this page.");return}if(this.currentTour={steps:e.sections.filter(o=>o.selector),currentStep:0,totalSteps:e.sections.filter(o=>o.selector).length},this.currentTour.totalSteps===0){window.TTSNotifications.warning("No interactive elements to tour on this page.");return}this.showTourStep()}showTourStep(){if(!this.currentTour||this.currentTour.currentStep>=this.currentTour.totalSteps){this.endTour();return}let t=this.currentTour.steps[this.currentTour.currentStep],e=document.querySelector(t.selector);if(!e){this.currentTour.currentStep++,this.showTourStep();return}document.querySelectorAll(".tts-tour-highlight").forEach(s=>{s.classList.remove("tts-tour-highlight")}),document.querySelectorAll(".tts-tour-tooltip").forEach(s=>{s.remove()}),e.classList.add("tts-tour-highlight"),e.scrollIntoView({behavior:"smooth",block:"center"});let o=this.createTourTooltip(t);this.positionTooltip(o,e),document.body.appendChild(o)}createTourTooltip(t){let e=document.createElement("div");return e.className="tts-tour-tooltip",e.innerHTML=`
            <div class="tts-tour-content">
                <h4 style="margin: 0 0 8px 0;">${t.title}</h4>
                <p style="margin: 0;">${t.content}</p>
            </div>
            <div class="tts-tour-controls">
                <div class="tts-tour-progress">
                    Step ${this.currentTour.currentStep+1} of ${this.currentTour.totalSteps}
                </div>
                <div class="tts-tour-buttons">
                    ${this.currentTour.currentStep>0?'<button class="tts-tour-btn secondary" data-action="prev">Previous</button>':""}
                    <button class="tts-tour-btn secondary" data-action="skip">Skip Tour</button>
                    <button class="tts-tour-btn" data-action="next">
                        ${this.currentTour.currentStep===this.currentTour.totalSteps-1?"Finish":"Next"}
                    </button>
                </div>
            </div>
        `,e.addEventListener("click",o=>{let s=o.target.getAttribute("data-action");s&&(o.preventDefault(),this.handleTourAction(s))}),e}positionTooltip(t,e){let o=e.getBoundingClientRect(),s=t.getBoundingClientRect(),i={width:window.innerWidth,height:window.innerHeight},n=o.bottom+15,r=o.left;n+200>i.height?(n=o.top-200-15,t.classList.add("top")):t.classList.add("bottom"),r+300>i.width&&(r=i.width-320),r<20&&(r=20),t.style.top=n+"px",t.style.left=r+"px"}handleTourAction(t){switch(t){case"next":this.currentTour.currentStep++,this.showTourStep();break;case"prev":this.currentTour.currentStep--,this.showTourStep();break;case"skip":this.endTour();break}}endTour(){document.querySelectorAll(".tts-tour-highlight").forEach(t=>{t.classList.remove("tts-tour-highlight")}),document.querySelectorAll(".tts-tour-tooltip").forEach(t=>{t.remove()}),this.currentTour=null,window.TTSNotifications.success("Tour completed! You can restart it anytime from the help menu.")}highlightElement(t){let e=document.querySelector(t);e&&(document.querySelectorAll(".tts-tour-highlight").forEach(o=>{o.classList.remove("tts-tour-highlight")}),e.classList.add("tts-tour-highlight"),e.scrollIntoView({behavior:"smooth",block:"center"}),setTimeout(()=>{e.classList.remove("tts-tour-highlight")},3e3))}showDocumentation(){let t=window.TTSAdminUtils.createModal({title:"Documentation & Resources",body:`
                <div class="tts-docs-content">
                    <div class="docs-section">
                        <h4>Quick Start Guide</h4>
                        <p>Get started with Social Auto Publisher in just a few steps:</p>
                        <ol>
                            <li>Configure your first client using the Client Wizard</li>
                            <li>Connect your social media accounts</li>
                            <li>Set up Trello board integration</li>
                            <li>Start creating and scheduling posts</li>
                        </ol>
                    </div>
                    
                    <div class="docs-section">
                        <h4>Key Features</h4>
                        <ul>
                            <li><strong>Multi-Platform Publishing:</strong> Facebook, Instagram, YouTube, TikTok</li>
                            <li><strong>Trello Integration:</strong> Manage posts directly from Trello</li>
                            <li><strong>Scheduling:</strong> Plan and schedule posts in advance</li>
                            <li><strong>Analytics:</strong> Track performance across all platforms</li>
                            <li><strong>Health Monitoring:</strong> Keep track of system status</li>
                        </ul>
                    </div>
                    
                    <div class="docs-section">
                        <h4>Troubleshooting</h4>
                        <p>Common issues and solutions:</p>
                        <ul>
                            <li><strong>Posts not publishing:</strong> Check Health Status for token issues</li>
                            <li><strong>Trello not connecting:</strong> Verify API key and token</li>
                            <li><strong>Missing posts:</strong> Check client configuration and board mapping</li>
                        </ul>
                    </div>
                    
                    <div class="docs-section">
                        <h4>Keyboard Shortcuts</h4>
                        <p>Speed up your workflow with these shortcuts:</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-family: monospace; font-size: 12px;">
                            <div>Ctrl+Shift+D \u2192 Dashboard</div>
                            <div>Ctrl+Shift+C \u2192 Calendar</div>
                            <div>Ctrl+Shift+A \u2192 Analytics</div>
                            <div>Ctrl+Shift+H \u2192 Health Status</div>
                            <div>Ctrl+Shift+L \u2192 Logs</div>
                            <div>Ctrl+Shift+N \u2192 New Client</div>
                            <div>Ctrl+Shift+R \u2192 Refresh</div>
                            <div>Ctrl+Shift+K \u2192 Show Shortcuts</div>
                        </div>
                    </div>
                </div>
            `,buttons:[{text:"Start Tour",class:"button",onclick:()=>{t.remove(),this.startGuidedTour()}},{text:"Close",class:"button-primary",onclick:function(){this.closest(".tts-modal-overlay").remove()}}]});window.TTSAdminUtils.showModal(t)}showGenericHelp(){window.TTSNotifications.info("Help is available! Use the help button in the admin bar or press F1 for contextual assistance.")}getCurrentPage(){return new URLSearchParams(window.location.search).get("page")||"unknown"}};document.addEventListener("DOMContentLoaded",()=>{window.location.href.includes("page=fp-publisher-")&&(window.TTSHelpSystem={instance:new a,highlightElement:function(l){this.instance.highlightElement(l)}})});})();
//# sourceMappingURL=tts-help-system-OL5XAGRN.js.map
