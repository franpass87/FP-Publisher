(()=>{var h=class{constructor(){this.container=null,this.notifications=new Map,this.init()}init(){if(this.container=document.createElement("div"),this.container.id="tts-notification-container",this.container.className="tts-notifications",this.container.setAttribute("aria-live","polite"),this.container.setAttribute("aria-atomic","false"),this.container.setAttribute("role","status"),document.body.appendChild(this.container),!document.getElementById("tts-notification-styles")){let i=document.createElement("style");i.id="tts-notification-styles",i.textContent=this.getNotificationStyles(),document.head.appendChild(i)}this.enhanceWordPressNotices(),this.setupKeyboardNavigation()}getNotificationStyles(){return`
            .tts-notifications {
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 100000;
                max-width: 400px;
                pointer-events: none;
            }
            
            .tts-notification {
                background: #fff;
                border-radius: 8px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border-left: 4px solid #135e96;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.320, 1.275);
                pointer-events: auto;
                position: relative;
                overflow: hidden;
            }
            
            .tts-notification.show {
                opacity: 1;
                transform: translateX(0);
            }
            
            .tts-notification.success {
                border-left-color: #00a32a;
            }
            
            .tts-notification.error {
                border-left-color: #d63638;
            }
            
            .tts-notification.warning {
                border-left-color: #f56e28;
            }
            
            .tts-notification.info {
                border-left-color: #135e96;
            }
            
            .tts-notification::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, currentColor, transparent);
                opacity: 0.3;
            }
            
            .tts-notification-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
            }
            
            .tts-notification-title {
                font-weight: 600;
                color: #1d2327;
                margin: 0;
                font-size: 14px;
            }
            
            .tts-notification-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #666;
                padding: 0;
                margin-left: 12px;
                transition: color 0.2s;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .tts-notification-close:hover {
                color: #333;
            }
            
            .tts-notification-message {
                color: #50575e;
                font-size: 13px;
                line-height: 1.4;
                margin: 0;
            }
            
            .tts-notification-actions {
                margin-top: 12px;
                display: flex;
                gap: 8px;
            }
            
            .tts-notification-action {
                background: #f0f0f1;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                color: #1d2327;
            }
            
            .tts-notification-action:hover {
                background: #dcdcde;
                text-decoration: none;
                color: #1d2327;
            }
            
            .tts-notification-action.primary {
                background: #135e96;
                color: #fff;
                border-color: #135e96;
            }
            
            .tts-notification-action.primary:hover {
                background: #0a4b78;
                color: #fff;
            }
            
            .tts-notification-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: currentColor;
                opacity: 0.3;
                transition: width linear;
            }
            
            .tts-notification.dismissing {
                opacity: 0;
                transform: translateX(100%) scale(0.9);
            }
            
            @media (max-width: 782px) {
                .tts-notifications {
                    top: 46px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        `}show(i){let{title:t="",message:e="",type:o="info",duration:l=5e3,actions:d=[],id:p=null,persistent:r=!1}=i;if(!e&&!t)return console.warn("TTSNotificationSystem: Empty notification message and title"),null;let s=p||"notification_"+Date.now()+"_"+Math.random().toString(36).substr(2,9);this.notifications.has(s)&&this.dismiss(s);try{let a=this.createNotificationElement({id:s,title:this.escapeHtml(t),message:this.escapeHtml(e),type:o,actions:d,persistent:r,duration:l});if(this.container.appendChild(a),this.notifications.set(s,a),this.announceToScreenReader(e||t,o),requestAnimationFrame(()=>{a.classList.add("show")}),!r&&l>0){let n=a.querySelector(".tts-notification-progress");n&&(n.style.width="100%",n.style.transition=`width ${l}ms linear`,requestAnimationFrame(()=>{n.style.width="0%"})),setTimeout(()=>{this.dismiss(s)},l)}return s}catch(a){return console.error("TTSNotificationSystem: Error creating notification:",a),null}}createNotificationElement(i){let{id:t,title:e,message:o,type:l,actions:d,persistent:p}=i,r=document.createElement("div");r.className=`tts-notification ${l}`,r.setAttribute("data-id",t);let s=document.createElement("div");if(s.className="tts-notification-header",e){let n=document.createElement("h4");n.className="tts-notification-title",n.textContent=e,s.appendChild(n)}let a=document.createElement("button");if(a.className="tts-notification-close",a.innerHTML="\xD7",a.onclick=()=>this.dismiss(t),s.appendChild(a),r.appendChild(s),o){let n=document.createElement("p");n.className="tts-notification-message",n.textContent=o,r.appendChild(n)}if(d&&d.length>0){let n=document.createElement("div");n.className="tts-notification-actions",d.forEach(c=>{let f=document.createElement(c.href?"a":"button");f.className=`tts-notification-action ${c.primary?"primary":""}`,f.textContent=c.label,c.href?f.href=c.href:c.onClick&&(f.onclick=c.onClick),n.appendChild(f)}),r.appendChild(n)}if(!p){let n=document.createElement("div");n.className="tts-notification-progress",r.appendChild(n)}return r}dismiss(i){let t=this.notifications.get(i);t&&(t.classList.add("dismissing"),setTimeout(()=>{t.parentNode&&t.parentNode.removeChild(t),this.notifications.delete(i)},400))}dismissAll(){this.notifications.forEach((i,t)=>{this.dismiss(t)})}enhanceWordPressNotices(){document.querySelectorAll(".notice:not(.tts-enhanced)").forEach(t=>{let e=t.classList.contains("notice-error")?"error":t.classList.contains("notice-warning")?"warning":t.classList.contains("notice-success")?"success":"info",o=t.textContent.trim();o&&(this.show({message:o,type:e,duration:8e3}),t.style.display="none",t.classList.add("tts-enhanced"))})}escapeHtml(i){if(!i)return"";let t={"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"};return i.replace(/[&<>"']/g,function(e){return t[e]})}announceToScreenReader(i,t){if(!i)return;let e=document.createElement("div");e.setAttribute("aria-live",t==="error"?"assertive":"polite"),e.setAttribute("aria-atomic","true"),e.className="sr-only",e.style.cssText="position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;",e.textContent=`${t}: ${i}`,document.body.appendChild(e),setTimeout(()=>{e.parentNode&&e.parentNode.removeChild(e)},1e3)}setupKeyboardNavigation(){document.addEventListener("keydown",i=>{if(i.key==="Escape"){let t=this.container.querySelectorAll(".tts-notification");if(t.length>0){let o=t[t.length-1].querySelector(".tts-notification-dismiss");o&&(o.click(),i.preventDefault())}}})}success(i,t={}){return this.show({...t,message:i,type:"success"})}error(i,t={}){return this.show({...t,message:i,type:"error",duration:0})}warning(i,t={}){return this.show({...t,message:i,type:"warning"})}info(i,t={}){return this.show({...t,message:i,type:"info"})}};window.TTSNotifications=new h;document.addEventListener("DOMContentLoaded",function(){new MutationObserver(function(i){i.forEach(function(t){t.addedNodes.forEach(function(e){e.nodeType===1&&e.classList&&e.classList.contains("notice")&&window.TTSNotifications.enhanceWordPressNotices()})})}).observe(document.body,{childList:!0,subtree:!0}),window.location.href.includes("page=tts-")&&setTimeout(()=>{window.TTSNotifications.info("Enhanced interface loaded successfully!",{duration:3e3})},1e3)});})();
//# sourceMappingURL=tts-notifications-B6RB54SS.js.map
