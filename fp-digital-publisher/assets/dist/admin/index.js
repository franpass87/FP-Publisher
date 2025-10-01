(()=>{var be=null;function vt(){var n;if(be)return be;let e=typeof window!="undefined"?window:globalThis,t=(n=e==null?void 0:e.wp)==null?void 0:n.i18n;if(!t||typeof t.__!="function"||typeof t.sprintf!="function")throw new Error('wp.i18n is not available. Ensure the "wp-i18n" script is enqueued.');return be={__:t.__.bind(t),sprintf:t.sprintf.bind(t)},be}var s=(e,t)=>vt().__(e,t),v=(e,...t)=>vt().sprintf(e,...t);var a="fp-publisher";function Ve(e){return typeof e=="string"?e.trim():""}function Et(e){return Array.isArray(e)?e.map(t=>Ve(t)).filter(t=>t!==""):[]}function ue(e){return Array.from(new Set(e.filter(t=>t!=="")))}var m={common:{close:s("Close",a)},composer:{header:s("Content composer",a),subtitle:s("Complete the key information before scheduling.",a),stepperLabel:s("Composer progress",a),steps:{content:s("Content",a),variants:s("Variants",a),media:s("Media",a),schedule:s("Schedule",a),review:s("Review",a)},fields:{title:{label:s("Content title",a),placeholder:s("E.g. New product launch",a)},caption:{label:s("Caption",a),placeholder:s("Tell the story of the content and add call-to-actions.",a),hint:s("Tip: include CTAs, mentions, and short links.",a)},schedule:{label:s("Schedule",a)}},hashtagToggle:{label:s("Hashtags in the first comment (IG)",a),description:s("Automatically move hashtags to the first comment to keep the caption clean.",a),previewTitle:s("Comment preview",a),previewBody:s(" #marketing #launchday #fpDigitalPublisher",a).trimStart()},actions:{saveDraft:s("Save draft",a),submit:s("Schedule content",a)},feedback:{blocking:s("Resolve the blocking items before scheduling.",a),scheduled:s("Content scheduled for %s.",a),fallbackDate:s("date to be defined",a),issuesPrefix:s("Fix: %s",a),noIssues:s("No blocking issues.",a),draftSaved:s("Draft saved in work-in-progress content.",a)},validation:{titleShort:s("Add a descriptive title (at least 5 characters).",a),captionShort:s("Fill the caption with at least 15 characters.",a),captionDetail:s("Add more details or CTAs in the caption.",a),scheduleInvalid:s("Set a future publication date.",a),hashtagsOff:s("Enable hashtags in the first comment to optimize IG reach.",a)}},preflight:{chipLabel:s("Preflight",a),modalTitle:s("Preflight details",a)},shortlinks:{empty:s("No short link configured. Create the first one to start tracking campaigns.",a),feedback:{loading:s("Loading short links\u2026",a),empty:s("No short link configured. Create the first one to track campaigns.",a),open:s("Opening %s in a new tab.",a),copySuccess:s("URL copied to the clipboard.",a),copyError:s("Unable to copy to the clipboard.",a),disabling:s("Disabling in progress\u2026",a),disabledEmpty:s("Short link disabled. There are no other active links.",a),disabled:s("Short link disabled successfully.",a),updated:s("Short link updated successfully.",a),created:s("Short link created successfully.",a)},section:{title:s("Short link",a),subtitle:s("Manage redirects and quick campaigns",a),createButton:s("New short link",a)},validation:{slugMissing:s("Enter a slug.",a),slugFormat:s("The slug can contain only letters, numbers, and hyphens.",a),targetMissing:s("Enter a destination URL.",a),targetInvalid:s("Enter a valid URL (e.g. https://example.com).",a)},preview:{shortlinkLabel:s("Short link:",a),utmLabel:s("UTM destination:",a),waiting:s("Waiting for a valid URL to compute the UTMs.",a)},errors:{disable:s("Error while disabling (%s).",a),save:s("Error while saving (%s).",a)},table:{slug:s("Slug",a),target:s("Destination",a),clicks:s("Clicks",a),lastClick:s("Last click",a),actions:s("Actions",a)},actions:{open:s("Open",a),copy:s("Copy URL",a),edit:s("Edit",a),disable:s("Disable",a)},menuLabel:s("Actions for %s",a),modal:{createTitle:s("New short link",a),editTitle:s("Edit short link",a),slugLabel:s("Slug",a),slugPlaceholder:s("promo-social",a),targetLabel:s("Destination URL",a),targetPlaceholder:s("https://example.com/promo",a),previewDefault:s("Fill the destination to generate the UTM preview.",a),cancel:s("Cancel",a),create:s("Create short link",a),update:s("Update link",a)}},trello:{modalTitle:s("Import content from Trello",a),listLabel:s("Trello list ID or URL",a),listPlaceholder:s("https://trello.com/b/.../list",a),apiKeyLabel:s("Trello API Key",a),tokenLabel:s("Trello Token",a),oauthLabel:s("OAuth Bearer token (optional)",a),oauthHint:s("Fill only if you use OAuth 2.0; leave empty to use API key + token.",a),fetch:s("Load cards",a),import:s("Import selection",a),loading:s("Loading Trello cards\u2026",a),empty:s("No cards available in the selected list.",a),selectionHint:s("Select one or more cards to import as drafts.",a),missingCredentials:s("Enter API key + token or an OAuth token.",a),missingList:s("Enter a valid list ID or URL.",a),noSelection:s("Select at least one Trello card to import.",a),success:s("%d cards imported as drafts.",a),errorLoading:s("Unable to fetch Trello cards: %s",a),errorImport:s("Unable to import the selection: %s",a),context:s("Content will be imported as drafts for %1$s \xB7 %2$s.",a),attachmentsLabel:s("%d attachments",a),viewCard:s("Open in Trello",a)}},Jt='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M5 4.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/><path d="M5 8.75A1.25 1.25 0 1 1 5 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 10 11a1.25 1.25 0 0 1 0-2.5zm5 0A1.25 1.25 0 1 1 15 11a1.25 1.25 0 0 1 0-2.5z"/><path d="M5 13.25a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5zm5 0a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5z"/></svg>',Xt=[{id:"title",label:s("Title",a),description:s("Use a descriptive title to help the team understand the focus of the content.",a),impact:30},{id:"caption",label:s("Caption",a),description:s("Complete the caption with call-to-actions and brand references.",a),impact:30},{id:"schedule",label:s("Scheduling",a),description:s("Set a future date and time to avoid conflicts with other content.",a),impact:25},{id:"hashtags",label:s("Hashtags",a),description:s("Confirm the hashtags in the first comment to increase Instagram reach.",a),impact:15}],$={title:"",caption:"",scheduledAt:"",hashtagsFirst:!1,issues:[],notes:[],score:100},C=new Map,Ne=null,R=[],_e=null,oe=null,U=null,Le=null,Zt=window,kt,T=(kt=Zt.fpPublisherAdmin)!=null?kt:{restBase:"",nonce:"",version:"0.0.0",brand:"",brands:[],channels:[]},ks=s("Select a plan from the calendar or kanban to inspect details.",a),Qt=s("%1$s \u2014 Status: %2$s",a),en=s("Next slot %s",a),tn=s("Advance to %s",a),nn=s("Plan advanced to %s.",a),sn=s("Plan #%1$d \u2014 %2$s",a),ne=s("Select a plan to review the approvals workflow.",a),ke=s("Select a plan to read the latest comments.",a),an=s("Unable to advance the plan (%s).",a),rn=s("Status changed from %1$s to %2$s.",a),on=s("Status set to %s.",a),ln=s("Approvals workflow updated for plan #%d.",a),cn=s("Comments updated for plan #%d.",a),dn=s("No comments available for plan #%d.",a),pn=s("Comment sent for plan #%d.",a),Re=s("No further approval actions available for the selected plan.",a),H=new Map,O=null,We=Et(T.brands),Ce=Et(T.channels),Ge=Ve(T.brand)||We[0]||"",j=Ce[0]||"instagram";T.brand=Ge;T.brands=We;T.channels=Ce;var Y=document.getElementById("fp-publisher-admin-app"),z=new Date,Ye=`${z.getFullYear()}-${String(z.getMonth()+1).padStart(2,"0")}`,Ee="comfort",Oe={"empty-week":{label:s("Empty week",a),endpoint:"alerts/empty-week",empty:s("No gap detected for the current week.",a)},"token-expiry":{label:s("Expiring tokens",a),endpoint:"alerts/token-expiry",empty:s("All tokens are up to date.",a)},"failed-jobs":{label:s("Failed jobs",a),endpoint:"alerts/failed-jobs",empty:s("No failed jobs in the last 24 hours.",a)}},un=ue([Ge,...We]),mn=ue([j,...Ce]),yt={info:s("Informational",a),warning:s("Warning",a),critical:s("Critical",a)},fn=s("All brands",a),Z="empty-week",le=Ge,ie=j,$t={ok:s("Operational",a),warning:s("Warning",a),error:s("Error",a)},hn={ok:"positive",warning:"warning",error:"danger"},gn=Array.from(new Set(["all",j,...Ce])),bn=["all","ok","warning","error"],$e="all",Se="all",Ae="",qe,Te=new Map,Fe=`${window.location.origin.replace(/\/$/,"")}/wp-admin/`,se={draft:s("Draft",a),ready:s("Ready for review",a),approved:s("Approved",a),scheduled:s("Scheduled",a),published:s("Published",a),failed:s("Needs revision",a),changes_requested:s("Changes requested",a)},vn={draft:"neutral",ready:"neutral",approved:"positive",scheduled:"positive",published:"positive",failed:"warning",changes_requested:"warning"},E={anchor:-1,query:"",suggestions:[],activeIndex:-1,list:null,textarea:null},ee,Ue=0;function St(e){return`${e.getFullYear()}-${String(e.getMonth()+1).padStart(2,"0")}-${String(e.getDate()).padStart(2,"0")}`}function yn(e){return e.toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"})}function At(e){return e.toLocaleDateString([],{weekday:"short",day:"numeric",month:"short"})}function l(e){return e.replace(/[&<>'"]/g,t=>{switch(t){case"&":return"&amp;";case"<":return"&lt;";case">":return"&gt;";case'"':return"&quot;";case"'":return"&#039;";default:return t}})}function _n(e,t){let n=t.replace(/[^a-zA-Z0-9_-]/g,"-").toLowerCase();return`${e}-${n}`}function wt(e,t=72){return e.length<=t?e:`${e.slice(0,t-1)}\u2026`}function Ln(e){if(!e)return"\u2014";let t=new Date(e);return Number.isNaN(t.getTime())?"\u2014":t.toLocaleString(void 0,{day:"2-digit",month:"short",year:"numeric",hour:"2-digit",minute:"2-digit"})}function me(e){return`${window.location.origin.replace(/\/$/,"")}/go/${e}`}function Tn(e){let t=e.split(/\s+/).filter(Boolean).slice(0,2);return t.length?t.map(n=>n.charAt(0).toUpperCase()).join(""):"??"}function kn(e){return l(e).replace(/(@[\w._-]+)/g,'<span class="fp-comments__mention-token">$1</span>').replace(/\n/g,"<br />")}function N(e){let t=document.getElementById("fp-comments-announcer");t&&(t.textContent=e)}function V(e){let t=document.getElementById("fp-approvals-announcer");t&&(t.textContent=e)}function te(e){let t=document.getElementById("fp-alerts-announcer");t&&(t.textContent=e)}function ce(e){let t=document.getElementById("fp-logs-announcer");t&&(t.textContent=e)}function Je(e){var n,r,o;let t=((o=(r=e.title)!=null?r:(n=e.template)==null?void 0:n.name)!=null?o:"").trim();return t||(e.id?v(s("Plan #%d",a),e.id):s("Untitled plan",a))}function D(e){return e&&e.split(/[-_\s]+/).filter(Boolean).map(t=>t.charAt(0).toUpperCase()+t.slice(1)).join(" ")}function _t(e,t){return e.filter(n=>n&&typeof n=="string").map(n=>{let r=n.trim(),o=r===t?" selected":"";return`<option value="${l(r)}"${o}>${l(D(r))}</option>`}).join("")}function Mt(e){return e.trim().toLowerCase()}function He(e){return!e||typeof e.id!="number"||!Number.isFinite(e.id)?null:e.id}function we(e){var r;return(r=(Array.isArray(e.slots)?e.slots:[]).map(o=>{if(!o||typeof o.scheduled_at!="string"||o.scheduled_at==="")return Number.POSITIVE_INFINITY;let i=new Date(o.scheduled_at);return Number.isNaN(i.getTime())?Number.POSITIVE_INFINITY:i.getTime()}).filter(o=>Number.isFinite(o)).sort((o,i)=>o-i)[0])!=null?r:Number.POSITIVE_INFINITY}function En(e){if(Array.isArray(e.channels)&&e.channels.length>0)return e.channels.filter(t=>typeof t=="string"&&t.trim()!=="");if(Array.isArray(e.slots)){let t=e.slots.map(n=>n&&typeof n.channel=="string"?n.channel:"").filter(n=>n!=="");if(t.length>0)return t}return[]}function It(e){let t=ue(En(e).map(n=>Mt(n)));return t.length===0?s("Channels pending",a):t.map(n=>D(n)).join(", ")}function Ct(e){let t=we(e);return Number.isFinite(t)?v(en,new Date(t).toLocaleString()):s("Schedule TBD",a)}function $n(e){let t=[Je(e)],n=e.brand?D(e.brand):"";n&&t.push(n);let r=It(e);r&&t.push(r);let o=Ct(e);return o&&t.push(o),t.filter(Boolean).join(" \xB7 ")}function Sn(e){let t=new Set;e.forEach(n=>{let r=He(n);r!==null&&(t.add(r),H.set(r,{...n}))}),Array.from(H.keys()).forEach(n=>{t.has(n)||H.delete(n)}),!(O!==null&&H.has(O))&&(O=Ht())}function Ht(){var t;let e=null;return H.forEach((n,r)=>{let o=we(n);(e===null||o<e.timestamp)&&(e={id:r,timestamp:o})}),(t=e==null?void 0:e.id)!=null?t:null}function I(){return O!==null&&H.has(O)?O:null}function Xe(){let e=I();return e!==null?H.get(e):void 0}function re(e){if(!e)return null;let t=Number(e);return Number.isFinite(t)&&t>0?t:null}function F(e){return Mt(e!=null?e:"")}function Pt(e,t){if(!H.has(e))return;let n=F(t),r=H.get(e);r&&H.set(e,{...r,status:n})}function Q(e,t=!1){let n=I(),r=null;if(e!==null&&H.has(e)?r=e:H.size>0&&(r=Ht()),n!==r)O=r;else if(t)O=r;else{fe(),r===null&&(Me(),he());return}fe(),Me(),he()}function An(){document.querySelectorAll(".fp-calendar__item").forEach(e=>{var r;let t=re((r=e.dataset.planId)!=null?r:null),n=t!==null&&t===I();e.classList.toggle("is-active",n),e.hasAttribute("role")&&e.setAttribute("aria-pressed",n?"true":"false")})}function xt(){let e=document.getElementById("fp-kanban");if(!e)return;["draft","ready","approved","scheduled","published","failed"].forEach(n=>{let r=e.querySelector(`.fp-kanban-column[data-status="${n}"]`);if(!r)return;let o=r.querySelector(`[data-count="${n}"]`),i=r.querySelector(".fp-kanban-column__list");if(!i||!o)return;let c=Array.from(H.values()).filter(u=>F(u.status)===n);if(o.textContent=String(c.length),c.length===0){i.innerHTML=`<p class="fp-kanban__empty">${l(s("No plans in this status.",a))}</p>`;return}i.innerHTML=c.sort((u,d)=>we(u)-we(d)).map(u=>wn(u)).join("")})}function wn(e){var i;let t=He(e);if(t===null)return"";let n=F(e.status),r=(i=se[n])!=null?i:D(n),o=["fp-kanban-card"];return t===I()&&o.push("is-active"),`
    <article class="${o.join(" ")}" data-plan-id="${t}" data-status="${l(n)}" role="button" tabindex="0">
      <h4>${l(Je(e))}</h4>
      <p class="fp-kanban-card__meta">${l(It(e))}</p>
      <p class="fp-kanban-card__meta">${l(Ct(e))}</p>
      <span class="fp-kanban-card__status">${l(r)}</span>
    </article>
  `}function Mn(){var p,h;let e=Xe(),t=document.getElementById("fp-plan-context"),n=document.getElementById("fp-comments-plan"),r=document.getElementById("fp-comments-form"),o=r==null?void 0:r.querySelector("textarea"),i=r==null?void 0:r.querySelector('button[type="submit"]');if(!e){t&&(t.textContent=ne),n&&(n.textContent=ke),r&&r.setAttribute("aria-disabled","true"),o&&o.setAttribute("disabled","true"),i&&(i.setAttribute("disabled","true"),i.removeAttribute("aria-busy"));return}let c=(h=se[F(e.status)])!=null?h:D((p=e.status)!=null?p:""),u=v(Qt,$n(e),c),d=He(e),f=d!==null?v(sn,d,u):u;t&&(t.textContent=f),n&&(n.textContent=f),r&&r.removeAttribute("aria-disabled"),o&&o.removeAttribute("disabled"),i&&(i.removeAttribute("disabled"),i.removeAttribute("aria-busy"))}var In={draft:"ready",ready:"approved",approved:"scheduled"};function Bt(e){var n,r;let t=F((n=e==null?void 0:e.status)!=null?n:"");return(r=In[t])!=null?r:null}function Ze(){var i;let e=document.getElementById("fp-approvals-advance"),t=document.getElementById("fp-approvals-action-hint"),n=Xe();if(!e)return;if(!n){e.disabled=!0,e.textContent=ne,e.removeAttribute("aria-busy"),delete e.dataset.nextStatus,t&&(t.textContent=ne);return}let r=Bt(n);if(!r){e.disabled=!0,e.textContent=Re,delete e.dataset.nextStatus,t&&(t.textContent=Re);return}let o=(i=se[r])!=null?i:D(r);e.disabled=!1,e.textContent=v(tn,o),e.dataset.nextStatus=r,t&&(t.textContent="")}function fe(){xt(),An(),Mn(),Ze()}function Dt(e){document.querySelectorAll("[data-alert-tab]").forEach(n=>{var i;let o=((i=n.dataset.alertTab)!=null?i:"empty-week")===e;n.classList.toggle("is-active",o),n.setAttribute("aria-selected",o?"true":"false"),n.setAttribute("tabindex",o?"0":"-1")})}function Cn(e){var t,n;if(e.action_href){let r=l(e.action_href),o=l((t=e.action_label)!=null?t:s("Open details",a));return`<a class="button fp-alerts__action" href="${r}" target="_blank" rel="noopener noreferrer">${o}</a>`}if(e.action_type){let r=l((n=e.action_label)!=null?n:s("Open details",a)),o=e.action_target?` data-alert-target="${l(e.action_target)}"`:"";return`<button type="button" class="button fp-alerts__action" data-alert-action="${e.action_type}"${o}>${r}</button>`}return""}function Hn(e){return e==="critical"?"danger":e==="warning"?"warning":"neutral"}function Pn(e){return`<ul class="fp-alerts__list" role="list">${e.map(n=>{var b,y;let r=(b=n.severity)!=null?b:"info",o=Hn(r),i=(y=yt[r])!=null?y:yt.info,c=n.occurred_at?`<time datetime="${l(n.occurred_at)}">${new Date(n.occurred_at).toLocaleString()}</time>`:"",u=[];n.meta&&u.push(l(n.meta));let d=u.length?`<p class="fp-alerts__meta">${u.join(" \xB7 ")}</p>`:"",f=n.detail?`<p class="fp-alerts__detail">${l(n.detail)}</p>`:"",p=Cn(n),h=p?`<div class="fp-alerts__actions">${p}</div>`:"";return`
        <li class="fp-alerts__item" role="listitem" data-severity="${l(r)}">
          <header class="fp-alerts__item-header">
            <span class="fp-status-badge" data-tone="${o}">${l(i)}</span>
            <div class="fp-alerts__item-heading">
              <strong>${l(n.title)}</strong>
              ${c}
            </div>
          </header>
          ${f}
          ${d}
          ${h}
        </li>
      `}).join("")}</ul>`}async function ve(e){let t=document.getElementById("fp-alerts-panel");if(!t)return;Z=e,Dt(e),t.innerHTML=`<p class="fp-alerts__loading">${l(s("Loading alerts\u2026",a))}</p>`;let n=Oe[e],r=new URLSearchParams;le&&r.set("brand",le),ie&&r.set("channel",ie);try{let o=r.toString(),i=`${T.restBase}/${n.endpoint}${o?`?${o}`:""}`,c=await P(i),u=Array.isArray(c.items)?c.items:[];if(!u.length){t.innerHTML=`<p class="fp-alerts__empty">${l(n.empty)}</p>`,te(n.empty);return}t.innerHTML=Pn(u),te(v(s("Updated %1$d alerts for the %2$s view.",a),u.length,n.label))}catch(o){t.innerHTML=`<p class="fp-alerts__error">${l(v(s("Unable to load alerts (%s).",a),o.message))}</p>`,te(s("Error while fetching alerts.",a))}}function xn(e){var u;let t=ue([le,(u=T.brand)!=null?u:"",...un]),n=ue([ie,j,...mn]),r=Object.keys(Oe);e.innerHTML=`
    <section class="fp-alerts" aria-labelledby="fp-alerts-title">
      <header class="fp-alerts__header">
        <div>
          <h2 id="fp-alerts-title">${l(s("Operational alerts",a))}</h2>
          <p class="fp-alerts__hint">${l(s("Weekly priorities for the marketing team.",a))}</p>
        </div>
        <div class="fp-alerts__filters">
          <label class="fp-alerts__filter">
            <span>${l(s("Brand",a))}</span>
            <select id="fp-alerts-brand">${_t(t,le)}</select>
          </label>
          <label class="fp-alerts__filter">
            <span>${l(s("Channel",a))}</span>
            <select id="fp-alerts-channel">${_t(n,ie)}</select>
          </label>
        </div>
      </header>
      <nav class="fp-alerts__tabs" role="tablist" aria-label="${l(s("Alert categories",a))}">
        ${r.map(d=>{let f=Oe[d],p=d===Z;return`<button type="button" class="fp-alerts__tab${p?" is-active":""}" role="tab" data-alert-tab="${d}" aria-controls="fp-alerts-panel" aria-selected="${p?"true":"false"}">${f.label}</button>`}).join("")}
      </nav>
      <div id="fp-alerts-panel" class="fp-alerts__panel" role="tabpanel" tabindex="0" aria-live="polite"></div>
      <div id="fp-alerts-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;let o=e.querySelector("#fp-alerts-brand");o==null||o.addEventListener("change",()=>{le=o.value,ve(Z)});let i=e.querySelector("#fp-alerts-channel");i==null||i.addEventListener("change",()=>{ie=i.value,ve(Z)}),e.querySelectorAll("[data-alert-tab]").forEach(d=>{d.addEventListener("click",f=>{var h;f.preventDefault();let p=(h=d.dataset.alertTab)!=null?h:"empty-week";ve(p)})}),e.addEventListener("click",d=>{let f=d.target.closest("[data-alert-action]");f&&(d.preventDefault(),Dn(f))}),Dt(Z),ve(Z)}function Bn(e){if(/^https?:/i.test(e))return e;let t=e.replace(/^\/+/,"");return`${Fe}${t}`}function Dn(e){var n,r,o;let t=(n=e.dataset.alertAction)!=null?n:null;if(t){if(t==="calendar"){let i=document.getElementById("fp-calendar");i==null||i.scrollIntoView({behavior:"smooth",block:"start"}),te(s("Focused the calendar to schedule the empty week.",a));return}if(t==="job"){let i=(r=e.dataset.alertTarget)!=null?r:"",c=i?`${Fe}admin.php?page=fp-jobs&job=${encodeURIComponent(i)}`:`${Fe}admin.php?page=fp-jobs`;window.open(c,"_blank","noopener"),te(s("Job opened in a new tab.",a));return}if(t==="token"){let i=(o=e.dataset.alertTarget)!=null?o:"admin.php?page=fp-integrations",c=Bn(i);window.open(c,"_blank","noopener"),te(s("Integrations page opened to renew the token.",a))}}}function Nn(e){return`<ul class="fp-logs__list-items" role="list">${e.map(n=>{var b,y;let r=(b=hn[n.status])!=null?b:"warning",o=(y=$t[n.status])!=null?y:D(n.status),i=new Date(n.created_at).toLocaleString(),c=!n.payload,u=!n.stack,d=s("Payload",a),f=s("Stack trace",a),p=s("Copy payload",a),h=s("Copy stack",a);return`
        <li class="fp-logs__entry" role="listitem" data-status="${l(n.status)}">
          <header class="fp-logs__entry-header">
            <span class="fp-status-badge" data-tone="${r}">${l(o)}</span>
            <div class="fp-logs__entry-meta">
              <span class="fp-logs__channel">${l(n.channel)}</span>
              <time datetime="${l(n.created_at)}">${i}</time>
            </div>
          </header>
          <p class="fp-logs__message">${l(n.message)}</p>
          <div class="fp-logs__blocks">
            <section class="fp-logs__block">
              <header class="fp-logs__block-header">
                <h4>${l(d)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="payload"
                  data-log-id="${l(n.id)}"
                  data-label="${l(p)}"
                  aria-label="${l(v(s("Copy payload for log %s",a),n.id))}"
                  ${c?"disabled":""}
                >${l(p)}</button>
              </header>
              <pre class="fp-logs__code">${n.payload?l(n.payload):"\u2014"}</pre>
            </section>
            <section class="fp-logs__block">
              <header class="fp-logs__block-header">
                <h4>${l(f)}</h4>
                <button
                  type="button"
                  class="button fp-logs__copy"
                  data-log-copy="stack"
                  data-log-id="${l(n.id)}"
                  data-label="${l(h)}"
                  aria-label="${l(v(s("Copy stack trace for log %s",a),n.id))}"
                  ${u?"disabled":""}
                >${l(h)}</button>
              </header>
              <pre class="fp-logs__code">${n.stack?l(n.stack):"\u2014"}</pre>
            </section>
          </div>
        </li>
      `}).join("")}</ul>`}async function ye(){let e=document.getElementById("fp-logs-list");if(!e)return;e.innerHTML=`<p class="fp-logs__loading">${l(s("Loading logs\u2026",a))}</p>`;let t=new URLSearchParams;T.brand&&t.set("brand",T.brand),$e!=="all"&&t.set("channel",$e),Se!=="all"&&t.set("status",Se),Ae&&t.set("search",Ae);try{let n=t.toString(),r=`${T.restBase}/logs${n?`?${n}`:""}`,o=await P(r),i=Array.isArray(o.items)?o.items:[];if(!i.length){e.innerHTML=`<p class="fp-logs__empty">${l(s("No logs found for the selected filters.",a))}</p>`,ce(s("No logs available for the selected filters.",a)),Te.clear();return}Te.clear(),i.forEach(c=>{Te.set(c.id,{payload:c.payload,stack:c.stack})}),e.innerHTML=Nn(i),ce(v(s("%d logs loaded.",a),i.length))}catch(n){e.innerHTML=`<p class="fp-logs__error">${l(v(s("Unable to load logs (%s).",a),n.message))}</p>`,ce(s("Error while fetching logs.",a))}}function qn(e){let t=gn.map(c=>{let u=c===$e,d=c==="all"?s("All channels",a):D(c);return`<button type="button" class="fp-logs__filter${u?" is-active":""}" data-log-channel="${c}" aria-pressed="${u?"true":"false"}">${d}</button>`}).join(""),n=bn.map(c=>{var f;let u=c===Se,d=c==="all"?s("All statuses",a):(f=$t[c])!=null?f:D(String(c));return`<button type="button" class="fp-logs__filter${u?" is-active":""}" data-log-status="${c}" aria-pressed="${u?"true":"false"}">${d}</button>`}).join("");e.innerHTML=`
    <section class="fp-logs" aria-labelledby="fp-logs-title">
      <header class="fp-logs__header">
        <div>
          <h2 id="fp-logs-title">${l(s("Operational logs",a))}</h2>
          <p class="fp-logs__hint">${l(s("Monitoring jobs and diagnostics in real time.",a))}</p>
        </div>
        <form class="fp-logs__search" role="search">
          <label class="screen-reader-text" for="fp-logs-search">${l(s("Search logs",a))}</label>
          <input
            type="search"
            id="fp-logs-search"
            placeholder="${l(s("Search by message or ID",a))}"
            value="${l(Ae)}"
          />
        </form>
      </header>
      <div class="fp-logs__filters" data-log-filter="channel" role="group" aria-label="${l(s("Filter by channel",a))}">
        ${t}
      </div>
      <div class="fp-logs__filters" data-log-filter="status" role="group" aria-label="${l(s("Filter by status",a))}">
        ${n}
      </div>
      <div id="fp-logs-list" class="fp-logs__list" aria-live="polite"></div>
      <div id="fp-logs-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>
  `;let r=e.querySelector("#fp-logs-search");r==null||r.addEventListener("input",()=>{Ae=r.value.trim(),qe&&window.clearTimeout(qe),qe=window.setTimeout(()=>{ye()},240)});let o=e.querySelector('[data-log-filter="channel"]');o==null||o.querySelectorAll("button[data-log-channel]").forEach(c=>{c.addEventListener("click",u=>{var f;u.preventDefault();let d=(f=c.dataset.logChannel)!=null?f:"all";$e=d,o.querySelectorAll("button[data-log-channel]").forEach(p=>{let h=p.dataset.logChannel===d;p.classList.toggle("is-active",h),p.setAttribute("aria-pressed",h?"true":"false")}),ye()})});let i=e.querySelector('[data-log-filter="status"]');i==null||i.querySelectorAll("button[data-log-status]").forEach(c=>{c.addEventListener("click",u=>{var f;u.preventDefault();let d=(f=c.dataset.logStatus)!=null?f:"all";Se=d,i.querySelectorAll("button[data-log-status]").forEach(p=>{let h=p.dataset.logStatus===d;p.classList.toggle("is-active",h),p.setAttribute("aria-pressed",h?"true":"false")}),ye()})}),e.addEventListener("click",c=>{let u=c.target.closest("[data-log-copy]");if(!u)return;c.preventDefault();let d=u.dataset.logCopy==="stack"?"stack":"payload";Rn(u,d)}),ye()}async function Un(e){if(navigator.clipboard&&typeof navigator.clipboard.writeText=="function"){await navigator.clipboard.writeText(e);return}let t=document.createElement("textarea");t.value=e,t.setAttribute("readonly","true"),t.style.position="fixed",t.style.opacity="0",document.body.appendChild(t),t.select(),document.execCommand("copy"),document.body.removeChild(t)}async function Rn(e,t){var c,u,d;if(e.disabled)return;let n=(c=e.dataset.logId)!=null?c:"";if(!n)return;let r=Te.get(n);if(!r)return;let o=t==="payload"?r.payload:r.stack;if(!o)return;let i=(d=(u=e.dataset.label)!=null?u:e.textContent)!=null?d:"";try{await Un(o),e.classList.add("is-copied"),e.textContent=s("Copied",a);let f=t==="payload"?s("Payload",a):s("Stack trace",a);ce(v(s("%s copied to the clipboard.",a),f))}catch(f){console.error(s("Unable to copy log",a),f),e.classList.add("has-error"),e.textContent=s("Copy error",a),ce(s("Unable to copy to the clipboard.",a))}finally{window.setTimeout(()=>{e.classList.remove("is-copied","has-error"),e.textContent=i},1800)}}function On(e){e.innerHTML=`
    <header class="fp-composer__header">
      <div>
        <h2>${m.composer.header}</h2>
        <p class="fp-composer__subtitle">${m.composer.subtitle}</p>
      </div>
      <button
        type="button"
        class="fp-preflight-chip"
        id="fp-preflight-chip"
        aria-haspopup="dialog"
        aria-controls="fp-preflight-modal"
        aria-expanded="false"
      >
        <span class="fp-preflight-chip__label">${m.preflight.chipLabel}</span>
        <span class="fp-preflight-chip__score" id="fp-preflight-chip-score" aria-live="polite">100</span>
      </button>
    </header>
    <nav class="fp-stepper" aria-label="${m.composer.stepperLabel}">
      <ol class="fp-stepper__list">
        <li class="fp-stepper__item is-active" data-step="content">
          <span class="fp-stepper__bullet" aria-hidden="true">1</span>
          <span class="fp-stepper__label">${m.composer.steps.content}</span>
        </li>
        <li class="fp-stepper__item" data-step="variants">
          <span class="fp-stepper__bullet" aria-hidden="true">2</span>
          <span class="fp-stepper__label">${m.composer.steps.variants}</span>
        </li>
        <li class="fp-stepper__item" data-step="media">
          <span class="fp-stepper__bullet" aria-hidden="true">3</span>
          <span class="fp-stepper__label">${m.composer.steps.media}</span>
        </li>
        <li class="fp-stepper__item" data-step="programma">
          <span class="fp-stepper__bullet" aria-hidden="true">4</span>
          <span class="fp-stepper__label">${m.composer.steps.schedule}</span>
        </li>
        <li class="fp-stepper__item" data-step="review">
          <span class="fp-stepper__bullet" aria-hidden="true">5</span>
          <span class="fp-stepper__label">${m.composer.steps.review}</span>
        </li>
      </ol>
    </nav>
    <form id="fp-composer-form" class="fp-composer__form" novalidate>
      <div class="fp-field">
        <label for="fp-composer-title">${m.composer.fields.title.label}</label>
        <input type="text" id="fp-composer-title" name="title" placeholder="${m.composer.fields.title.placeholder}" required />
      </div>
      <div class="fp-field">
        <label for="fp-composer-caption">${m.composer.fields.caption.label}</label>
        <textarea
          id="fp-composer-caption"
          name="caption"
          rows="4"
          placeholder="${m.composer.fields.caption.placeholder}"
          required
        ></textarea>
        <p class="fp-field__hint">${m.composer.fields.caption.hint}</p>
      </div>
      <div class="fp-field fp-field--inline">
        <label for="fp-composer-schedule">${m.composer.fields.schedule.label}</label>
        <input type="datetime-local" id="fp-composer-schedule" name="scheduled_at" required />
      </div>
      <div class="fp-composer__toggle">
        <label class="fp-switch" for="fp-hashtag-toggle">
          <input
            type="checkbox"
            id="fp-hashtag-toggle"
            aria-describedby="fp-hashtag-hint"
            aria-controls="fp-hashtag-preview"
            aria-expanded="false"
          />
          <span class="fp-switch__control" aria-hidden="true"></span>
          <span class="fp-switch__label">${m.composer.hashtagToggle.label}</span>
        </label>
        <p id="fp-hashtag-hint" class="fp-composer__hint">
          ${m.composer.hashtagToggle.description}
        </p>
      </div>
      <section id="fp-hashtag-preview" class="fp-composer__preview" hidden aria-live="polite">
        <h3>${m.composer.hashtagToggle.previewTitle}</h3>
        <p>${m.composer.hashtagToggle.previewBody}</p>
      </section>
      <div class="fp-composer__actions">
        <button type="button" class="button" id="fp-composer-save-draft">${m.composer.actions.saveDraft}</button>
        <button type="submit" class="button button-primary" id="fp-composer-submit" data-tooltip-position="top">
          ${m.composer.actions.submit}
        </button>
      </div>
      <p id="fp-composer-issues" class="fp-composer__issues" role="status" aria-live="polite"></p>
      <div id="fp-composer-feedback" class="fp-composer__feedback" aria-live="polite"></div>
    </form>
    <div class="fp-modal" id="fp-preflight-modal" role="dialog" aria-modal="true" aria-labelledby="fp-preflight-title" hidden>
      <div class="fp-modal__backdrop" data-modal-overlay></div>
      <div class="fp-modal__dialog" role="document">
        <header class="fp-modal__header">
          <h2 id="fp-preflight-title">${m.preflight.modalTitle}</h2>
          <button type="button" class="fp-modal__close" data-modal-close aria-label="${l(m.common.close)}">\xD7</button>
        </header>
        <p id="fp-preflight-score" class="fp-modal__score" aria-live="polite"></p>
        <ul id="fp-preflight-list" class="fp-modal__list"></ul>
      </div>
    </div>
  `}function Fn(){var tt,nt,st,at,rt,ot,lt,it,ct,dt,pt,ut,mt;let e=document.getElementById("fp-composer");if(!e)return;let t=e.querySelector("#fp-composer-form"),n=(tt=t==null?void 0:t.querySelector("#fp-composer-title"))!=null?tt:null,r=(nt=t==null?void 0:t.querySelector("#fp-composer-caption"))!=null?nt:null,o=(st=t==null?void 0:t.querySelector("#fp-composer-schedule"))!=null?st:null,i=(at=t==null?void 0:t.querySelector("#fp-composer-submit"))!=null?at:null,c=(rt=t==null?void 0:t.querySelector("#fp-composer-save-draft"))!=null?rt:null,u=(ot=t==null?void 0:t.querySelector("#fp-composer-issues"))!=null?ot:null,d=(lt=t==null?void 0:t.querySelector("#fp-composer-feedback"))!=null?lt:null,f=(it=t==null?void 0:t.querySelector("#fp-hashtag-toggle"))!=null?it:null,p=(ct=t==null?void 0:t.querySelector("#fp-hashtag-preview"))!=null?ct:null,h=Array.from(e.querySelectorAll(".fp-stepper__item")),b=e.querySelector("#fp-preflight-chip"),y=e.querySelector("#fp-preflight-chip-score"),g=e.querySelector("#fp-preflight-modal"),L=(dt=g==null?void 0:g.querySelector("#fp-preflight-list"))!=null?dt:null,k=(pt=g==null?void 0:g.querySelector("#fp-preflight-score"))!=null?pt:null,w=(ut=g==null?void 0:g.querySelector("[data-modal-close]"))!=null?ut:null,S=(mt=g==null?void 0:g.querySelector("[data-modal-overlay]"))!=null?mt:null;if(!t||!n||!r||!o||!i||!b||!y||!g||!L||!k||!w||!S||!u||!d||!f||!p)return;let B=_=>Array.from(_.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])')).filter(A=>A.offsetParent!==null),K=()=>{k.textContent=`Score complessivo: ${$.score}/100`,L.innerHTML=Xt.map(_=>{let A=C.get(_.id)===!0,M=A?"Completato":"Da rivedere";return`
        <li class="fp-modal__item" data-status="${A?"done":"pending"}">
          <div>
            <span class="fp-modal__item-label">${_.label}</span>
            <span class="fp-modal__item-status">${M}</span>
          </div>
          <p>${_.description}</p>
        </li>
      `}).join("")},Pe=()=>{let _=$.score>=80?"positive":$.score>=60?"warning":"danger";b.dataset.tone=_,y.textContent=String($.score),K()},xe=()=>{var ht;let _=C.get("title")===!0,A=C.get("caption")===!0,M=C.get("schedule")===!0,W=C.get("hashtags")===!0,ae=["content","variants","media","programma","review"],ft={content:_,variants:A,media:A,programma:M,review:M&&W},Yt=(ht=ae.find(G=>!ft[G]))!=null?ht:"review";h.forEach(G=>{var bt;let gt=(bt=G.dataset.step)!=null?bt:"";if(G.classList.remove("is-active","is-complete","is-upcoming"),ft[gt]){G.classList.add("is-complete");return}gt===Yt?G.classList.add("is-active"):G.classList.add("is-upcoming")})},Be=()=>{let _=$.issues,A=_.length>0?v(m.composer.feedback.issuesPrefix,_.join(" \xB7 ")):m.composer.feedback.noIssues;u.textContent=A,_.length>0?u.classList.add("is-error"):u.classList.remove("is-error")},Qe=()=>{let _=$.issues.join(`
`);$.issues.length>0?(i.disabled=!0,i.dataset.tooltip=_,i.setAttribute("aria-describedby",u.id)):(i.disabled=!1,i.removeAttribute("data-tooltip"),i.removeAttribute("aria-describedby"))},Wt=()=>{$.hashtagsFirst?(p.removeAttribute("hidden"),f.setAttribute("aria-expanded","true")):(p.setAttribute("hidden",""),f.setAttribute("aria-expanded","false"))},X=()=>{$.title=n.value,$.caption=r.value,$.scheduledAt=o.value,$.hashtagsFirst=f.checked,$.issues=[],$.notes=[],C.clear();let _=100;$.title.trim().length<5?($.issues.push(m.composer.validation.titleShort),C.set("title",!1),_-=30):C.set("title",!0);let M=$.caption.trim();M.length<15?($.issues.push(m.composer.validation.captionShort),C.set("caption",!1),_-=30):(C.set("caption",!0),M.length<80&&$.notes.push(m.composer.validation.captionDetail));let W=$.scheduledAt,ae=W?new Date(W):null;!ae||Number.isNaN(ae.getTime())||ae.getTime()<=Date.now()?($.issues.push(m.composer.validation.scheduleInvalid),C.set("schedule",!1),_-=25):C.set("schedule",!0),$.hashtagsFirst?C.set("hashtags",!0):(C.set("hashtags",!1),$.notes.push(m.composer.validation.hashtagsOff),_-=10),$.score=Math.max(0,Math.min(100,_)),Pe(),xe(),Be(),Qe(),Wt(),d.textContent&&(d.textContent="",d.classList.remove("is-success","is-error"))},De=()=>{g.setAttribute("hidden",""),g.classList.remove("is-open"),g.removeEventListener("keydown",et),b.setAttribute("aria-expanded","false"),Ne&&Ne.focus()},et=_=>{if(_.key==="Escape"){_.preventDefault(),De();return}if(_.key==="Tab"){let A=B(g);if(A.length===0){_.preventDefault();return}let M=A[0],W=A[A.length-1];_.shiftKey?document.activeElement===M&&(_.preventDefault(),W.focus()):document.activeElement===W&&(_.preventDefault(),M.focus())}},Gt=()=>{var A,M;Ne=(A=document.activeElement)!=null?A:null,g.removeAttribute("hidden"),g.classList.add("is-open"),g.addEventListener("keydown",et),b.setAttribute("aria-expanded","true"),((M=B(g)[0])!=null?M:w).focus()};n.addEventListener("input",X),r.addEventListener("input",X),o.addEventListener("input",X),f.addEventListener("change",()=>{$.hashtagsFirst=f.checked,X()}),b.addEventListener("click",_=>{_.preventDefault(),Gt()}),w.addEventListener("click",_=>{_.preventDefault(),De()}),S.addEventListener("click",()=>{De()}),t.addEventListener("submit",_=>{if(_.preventDefault(),X(),$.issues.length>0){d.textContent=m.composer.feedback.blocking,d.classList.remove("is-success"),d.classList.add("is-error");return}let A=$.scheduledAt?new Date($.scheduledAt):null,M=A?A.toLocaleString():m.composer.feedback.fallbackDate;d.textContent=v(m.composer.feedback.scheduled,M),d.classList.remove("is-error"),d.classList.add("is-success")}),c==null||c.addEventListener("click",_=>{_.preventDefault(),d.textContent=m.composer.feedback.draftSaved,d.classList.remove("is-error"),d.classList.add("is-success")}),X()}async function Nt(e){var n;jn(e);let t=new URLSearchParams({channel:j,month:Ye});T.brand&&t.set("brand",T.brand);try{let r=await P(`${T.restBase}/plans?${t.toString()}`),o=Array.isArray(r.items)?r.items:[];if(o.length===0){H.clear(),O=null,Kn(e),Q(null,!0);return}Sn(o),Vn(e,o),Q(I(),!0)}catch(r){let o=(n=r==null?void 0:r.message)!=null?n:s("Unknown error",a);e.innerHTML=`<p class="fp-calendar__error">${l(v(s("Unable to load the calendar (%s).",a),o))}</p>`}}function jn(e){let t=Array.from({length:6}).map(()=>'<div class="fp-calendar__skeleton-card" aria-hidden="true"><div class="fp-calendar__skeleton-bar"></div><div class="fp-calendar__skeleton-bar is-short"></div></div>').join("");e.innerHTML=`
    <div class="fp-calendar__skeleton" role="status" aria-live="polite">
      <span class="screen-reader-text">${l(s("Loading schedules\u2026",a))}</span>
      ${t}
    </div>
  `}function Kn(e){e.innerHTML=`
    <div class="fp-calendar__empty" role="alert">
      <h3>${l(s("Empty calendar",a))}</h3>
      <p>${l(s("Import schedules from Trello to get started.",a))}</p>
      <button type="button" class="button button-primary" data-action="calendar-import">${l(s("Import from Trello",a))}</button>
    </div>
  `}function zn(e){let t=new Map;return e.forEach(n=>{var u;if(!n)return;let r=Array.isArray(n.slots)?n.slots:[],o=He(n),i=Je(n),c=F((u=n.status)!=null?u:"");r.forEach((d,f)=>{var L;if(!d||typeof d.scheduled_at!="string"||d.scheduled_at==="")return;let p=new Date(d.scheduled_at);if(Number.isNaN(p.getTime()))return;let h=St(p),b=typeof d.channel=="string"&&d.channel!==""?d.channel:j,y={id:`${(L=n.id)!=null?L:"plan"}-${f}`,planId:o,title:i,status:c,channel:b,isoDate:h,timeLabel:yn(p),timestamp:p.getTime()},g=t.get(h);g?g.push(y):t.set(h,[y])})}),t.forEach(n=>{n.sort((r,o)=>r.timestamp-o.timestamp)}),t}function Vn(e,t){var f;let n=zn(t),r=new Date(z.getFullYear(),z.getMonth(),1),o=new Date(z.getFullYear(),z.getMonth()+1,0).getDate(),i=[s("Mon",a),s("Tue",a),s("Wed",a),s("Thu",a),s("Fri",a),s("Sat",a),s("Sun",a)],c='<table class="fp-publisher-calendar"><thead><tr>';c+=i.map(p=>`<th scope="col">${p}</th>`).join(""),c+="</tr></thead><tbody>";let u=1,d=(r.getDay()+6)%7;for(let p=0;p<6&&u<=o;p+=1){c+="<tr>";for(let h=0;h<7;h+=1){if(p*7+h<d||u>o){c+='<td class="is-empty" aria-disabled="true"></td>';continue}let y=new Date(z.getFullYear(),z.getMonth(),u),g=St(y),L=(f=n.get(g))!=null?f:[],k=L.map(S=>{let B=`${S.title} \u2014 ${S.channel} \u2022 ${S.timeLabel}`,K=`${S.channel} \xB7 ${S.timeLabel}`,Pe=S.planId!==null&&S.planId===I(),xe=S.planId!==null?` data-plan-id="${S.planId}"`:"",Be=S.planId!==null?' role="button" tabindex="0"':"";return`
            <article class="${["fp-calendar__item",Pe?"is-active":""].filter(Boolean).join(" ")}" data-status="${l(S.status)}"${xe}${Be} title="${l(B)}">
              <span class="fp-calendar__item-handle" aria-hidden="true">${Jt}</span>
              <div class="fp-calendar__item-body">
                <span class="fp-calendar__item-title">${l(S.title)}</span>
                <span class="fp-calendar__item-meta">${l(K)}</span>
              </div>
            </article>
          `}).join(""),w=`
        <button
          type="button"
          class="fp-calendar__slot-action"
          data-date="${g}"
          aria-label="${l(v(s("Suggest a time for %s",a),At(y)))}"
        >${l(s("Suggest time",a))}</button>
      `;c+=`
        <td data-date="${g}">
          <div class="fp-calendar__cell">
            <span class="fp-calendar-day">${u}</span>
            <div class="fp-calendar__items">${k}</div>
            ${L.length===0?w:""}
          </div>
        </td>
      `,u+=1}c+="</tr>"}c+="</tbody></table>",e.innerHTML=c,qt(e)}function qt(e){let t=e.querySelector(".fp-publisher-calendar");t&&(Ee==="compact"?t.classList.add("is-compact"):t.classList.remove("is-compact"))}function Ut(){document.querySelectorAll("[data-calendar-density]").forEach(t=>{let r=(t.dataset.calendarDensity==="compact"?"compact":"comfort")===Ee;t.classList.toggle("is-active",r),t.setAttribute("aria-pressed",r?"true":"false")})}function Wn(e){if(Ee===e)return;Ee=e;let t=document.getElementById("fp-calendar");t&&qt(t),Ut()}async function Gn(e,t){var r,o;if(!t)return;let n=(r=e.textContent)!=null?r:"";e.disabled=!0,e.textContent=s("Loading\u2026",a);try{await Ke(t),(o=document.getElementById("fp-besttime-section"))==null||o.scrollIntoView({behavior:"smooth",block:"start"})}finally{e.textContent=n,e.disabled=!1}}function Yn(e){Jn(e)}function Jn(e){let t=document.getElementById("fp-trello-modal");t&&t.remove();let n=document.createElement("div");n.className="fp-modal",n.id="fp-trello-modal",n.setAttribute("role","dialog"),n.setAttribute("aria-modal","true"),n.setAttribute("aria-labelledby","fp-trello-modal-title");let r=T.brand||fn;n.innerHTML=`
    <div class="fp-modal__backdrop" data-trello-modal-overlay></div>
    <div class="fp-modal__dialog" role="document">
      <header class="fp-modal__header">
        <h2 id="fp-trello-modal-title">${l(m.trello.modalTitle)}</h2>
        <button type="button" class="fp-modal__close" data-trello-modal-close aria-label="${l(m.common.close)}">\xD7</button>
      </header>
      <form id="fp-trello-modal-form" class="fp-trello__form" novalidate>
        <p class="fp-trello__context">${l(v(m.trello.context,r,j))}</p>
        <label class="fp-trello__field">
          <span>${l(m.trello.listLabel)}</span>
          <input type="text" name="list_id" placeholder="${l(m.trello.listPlaceholder)}" autocomplete="off" required />
        </label>
        <label class="fp-trello__field">
          <span>${l(m.trello.apiKeyLabel)}</span>
          <input type="text" name="api_key" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${l(m.trello.tokenLabel)}</span>
          <input type="text" name="token" autocomplete="off" />
        </label>
        <label class="fp-trello__field">
          <span>${l(m.trello.oauthLabel)}</span>
          <input type="text" name="oauth_token" autocomplete="off" />
          <small class="fp-trello__hint">${l(m.trello.oauthHint)}</small>
        </label>
        <footer class="fp-modal__footer fp-trello__actions">
          <button type="button" class="button" data-trello-modal-close>${l(m.common.close)}</button>
          <button type="button" class="button" data-trello-fetch>${l(m.trello.fetch)}</button>
          <button type="button" class="button button-primary" data-trello-import disabled>${l(m.trello.import)}</button>
        </footer>
        <p id="fp-trello-modal-feedback" class="fp-trello__feedback" role="status" aria-live="polite" hidden></p>
        <div id="fp-trello-modal-cards" class="fp-trello__cards" role="group" aria-live="polite"></div>
      </form>
    </div>
  `,document.body.appendChild(n);let o=e instanceof HTMLElement?e:null,i=()=>{n.remove(),o&&o.focus()};n.querySelectorAll("[data-trello-modal-close], [data-trello-modal-overlay]").forEach(g=>{g.addEventListener("click",L=>{L.preventDefault(),i()})});let c=n.querySelector("#fp-trello-modal-form"),u=n.querySelector("[data-trello-fetch]"),d=n.querySelector("[data-trello-import]"),f=n.querySelector("#fp-trello-modal-feedback"),p=n.querySelector("#fp-trello-modal-cards"),h=n.querySelector('input[name="list_id"]');if(h==null||h.focus(),!c||!u||!d||!f||!p)return;let b=[];c.addEventListener("submit",g=>{g.preventDefault()});let y=()=>{b=[],Tt(p,b),d.disabled=!0};u.addEventListener("click",async g=>{var k;g.preventDefault();let L=Lt(c);if(!L.listId){q(f,m.trello.missingList,"error"),y();return}if(!L.oauthToken&&(L.apiKey===""||L.token==="")){q(f,m.trello.missingCredentials,"error"),y();return}q(f,m.trello.loading,"info"),u.disabled=!0,d.disabled=!0;try{b=await Zn(L),Tt(p,b),b.length===0?(q(f,m.trello.empty,"info"),d.disabled=!0):(q(f,"","info"),d.disabled=!1)}catch(w){let S=(k=w==null?void 0:w.message)!=null?k:s("Error",a);q(f,v(m.trello.errorLoading,S),"error"),y()}finally{u.disabled=!1}}),d.addEventListener("click",async g=>{var w,S;g.preventDefault();let L=Array.from(p.querySelectorAll('input[name="trello-card"]:checked')).map(B=>B.value);if(L.length===0){q(f,m.trello.noSelection,"error");return}let k=Lt(c);!k.listId&&h&&(k.listId=Rt((w=h.value)!=null?w:"")),q(f,m.trello.loading,"info"),d.disabled=!0,u.disabled=!0;try{let B=await Qn(k,L);q(f,v(m.trello.success,B.length),"success");let K=document.getElementById("fp-calendar");K&&await Nt(K),window.setTimeout(()=>{i()},1200)}catch(B){let K=(S=B==null?void 0:B.message)!=null?S:s("Error",a);q(f,v(m.trello.errorImport,K),"error")}finally{d.disabled=!1,u.disabled=!1}})}function Lt(e){var i,c,u,d,f,p,h,b;let t=((c=(i=e.querySelector('input[name="api_key"]'))==null?void 0:i.value)!=null?c:"").trim(),n=((d=(u=e.querySelector('input[name="token"]'))==null?void 0:u.value)!=null?d:"").trim(),r=((p=(f=e.querySelector('input[name="oauth_token"]'))==null?void 0:f.value)!=null?p:"").trim(),o=((b=(h=e.querySelector('input[name="list_id"]'))==null?void 0:h.value)!=null?b:"").trim();return{apiKey:t,token:n,oauthToken:r,listId:Rt(o),brand:Ve(T.brand),channel:j}}function Rt(e){let t=e.trim();if(t==="")return"";let n=t.match(/\/lists?\/([a-zA-Z0-9]+)/);if(n)return n[1];let r=t.split(/[/?#]/).filter(o=>o!=="");return r.length>0?r[r.length-1]:t}function Tt(e,t){if(t.length===0){e.innerHTML="";return}let n=t.map(r=>{var p;let o=Xn((p=r.due)!=null?p:null),i=Array.isArray(r.attachments)?r.attachments.length:0,c=i>0?v(m.trello.attachmentsLabel,i):"",u=typeof r.description=="string"&&r.description.trim()!==""?`<p>${l(r.description)}</p>`:"",d=[];o&&d.push(l(o)),c&&d.push(l(c)),r.url&&d.push(`<a href="${l(r.url)}" target="_blank" rel="noreferrer">${l(m.trello.viewCard)}</a>`);let f=d.length>0?`<p class="fp-trello__card-meta">${d.join(" \xB7 ")}</p>`:"";return`
        <li class="fp-trello__card">
          <label>
            <input type="checkbox" name="trello-card" value="${l(r.id)}" />
            <span class="fp-trello__card-body">
              <strong>${l(r.name)}</strong>
              ${f}
              ${u}
            </span>
          </label>
        </li>
      `}).join("");e.innerHTML=`
    <p class="fp-trello__hint">${l(m.trello.selectionHint)}</p>
    <ul class="fp-trello__cards-list">${n}</ul>
  `}function Xn(e){if(!e)return"";let t=new Date(e);if(Number.isNaN(t.getTime()))return"";let n=t.toLocaleDateString(),r=t.toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"});return`${n} \xB7 ${r}`}function q(e,t,n){let r=t.trim();if(r===""){e.textContent="",e.setAttribute("hidden",""),e.removeAttribute("data-tone");return}e.textContent=r,e.dataset.tone=n,e.removeAttribute("hidden")}async function Zn(e){let t={list_id:e.listId};e.apiKey!==""&&(t.api_key=e.apiKey),e.token!==""&&(t.token=e.token),e.oauthToken!==""&&(t.oauth_token=e.oauthToken);let n=await P(`${T.restBase}/ingest/trello/cards`,{method:"POST",body:JSON.stringify({payload:t})});return(Array.isArray(n.cards)?n.cards:[]).map(o=>({...o,attachments:Array.isArray(o.attachments)?o.attachments:[],description:typeof o.description=="string"?o.description:""}))}async function Qn(e,t){let n={brand:e.brand,channel:e.channel,list_id:e.listId,card_ids:t};e.apiKey!==""&&(n.api_key=e.apiKey),e.token!==""&&(n.token=e.token),e.oauthToken!==""&&(n.oauth_token=e.oauthToken);let r=await P(`${T.restBase}/ingest/trello`,{method:"POST",body:JSON.stringify({payload:n})});return Array.isArray(r.plans)?r.plans:[]}function es(e){let t=["draft","ready","approved","scheduled","published","failed"],n={draft:s("Drafts",a),ready:s("Ready",a),approved:s("Approved",a),scheduled:s("Scheduled",a),published:s("Published",a),failed:s("Failed",a)};e.innerHTML=t.map(r=>{var o;return`
        <section class="fp-kanban-column" data-status="${r}">
          <header class="fp-kanban-column__header">
            <h3>${(o=n[r])!=null?o:r}</h3>
            <span class="fp-kanban-column__count" data-count="${r}">0</span>
          </header>
          <div class="fp-kanban-column__list" aria-live="polite"></div>
        </section>
      `}).join("")}function ts(e){e.innerHTML=`
    <section class="fp-approvals">
      <header class="fp-approvals__header">
        <div>
          <h3>${l(s("Approvals workflow",a))}</h3>
          <p class="fp-approvals__hint">${l(s("Monitor key decisions and close them with one click.",a))}</p>
          <p id="fp-plan-context" class="fp-approvals__plan" aria-live="polite"></p>
        </div>
        <div class="fp-approvals__actions">
          <button type="button" class="button button-primary" id="fp-approvals-advance">${l(s("Advance status",a))}</button>
        </div>
      </header>
      <p id="fp-approvals-action-hint" class="fp-approvals__hint" aria-live="polite"></p>
      <ol id="fp-approvals-timeline" class="fp-approvals__timeline" aria-live="polite"></ol>
      <div id="fp-approvals-announcer" class="screen-reader-text" aria-live="polite"></div>
    </section>

    <section class="fp-comments__section">
      <header class="fp-comments__header">
        <div>
          <h3>${l(s("Plan comments",a))}</h3>
          <p class="fp-comments__hint" id="fp-comments-hint">${l(s("Use @ to mention a teammate and notify your feedback.",a))}</p>
          <p id="fp-comments-plan" class="fp-comments__plan" aria-live="polite"></p>
        </div>
        <button type="button" class="button" id="fp-refresh-comments">${l(s("Refresh",a))}</button>
      </header>
      <div id="fp-comments-list" class="fp-comments__list" aria-live="polite"></div>
      <form id="fp-comments-form" class="fp-comments__form">
        <label class="fp-comments__field">
          <span class="screen-reader-text">${l(s("New comment",a))}</span>
          <textarea
            name="body"
            rows="3"
            required
            placeholder="${l(s("Write a comment\u2026",a))}"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-owns="fp-mentions-list"
            aria-describedby="fp-comments-hint"
          ></textarea>
        </label>
        <ul
          id="fp-mentions-list"
          class="fp-comments__mentions"
          role="listbox"
          aria-label="${l(s("Mention suggestions",a))}"
          hidden
        ></ul>
        <div class="fp-comments__submit">
          <span class="fp-comments__hint">${l(s("Comments notify the editorial team.",a))}</span>
          <button type="submit" class="button button-primary">${l(s("Send",a))}</button>
        </div>
        <div id="fp-comments-announcer" class="screen-reader-text" aria-live="polite"></div>
      </form>
    </section>
  `}function ns(e){var n;let t=F(e);return(n=vn[t])!=null?n:"neutral"}function Ot(e){var d,f;let t=F(e.status),n=ns(e.status),r=(d=se[t])!=null?d:D(t),o=e.from?F(e.from):"",i=o?(f=se[o])!=null?f:D(o):"",c=i&&i!==r?v(rn,i,r):v(on,r),u=e.note?`<p class="fp-approvals__note">${l(e.note)}</p>`:"";return`
    <li class="fp-approvals__item">
      <span class="fp-approvals__avatar" aria-hidden="true">${Tn(e.actor.display_name)}</span>
      <div class="fp-approvals__content">
        <header class="fp-approvals__meta">
          <strong>${l(e.actor.display_name)}</strong>
          <time>${new Date(e.occurred_at).toLocaleString()}</time>
        </header>
        <span class="fp-approvals__badge" data-tone="${n}">${l(r)}</span>
        <p class="fp-approvals__summary">${l(c)}</p>
        ${u}
      </div>
    </li>
  `}async function Me(){let e=document.getElementById("fp-approvals-timeline");if(!e)return;let t=I();if(t===null){e.innerHTML=`<li class="fp-approvals__placeholder">${l(ne)}</li>`,V(ne);return}let n=t;e.innerHTML=`<li class="fp-approvals__placeholder">${l(s("Loading workflow\u2026",a))}</li>`;try{let r=await P(`${T.restBase}/plans/${n}/approvals`);if(I()!==n)return;typeof r.status=="string"&&r.status!==""&&(Pt(n,r.status),fe());let o=Array.isArray(r.items)?r.items:[];if(!o.length){e.innerHTML=`<li class="fp-approvals__placeholder">${l(s("No activity recorded in the workflow.",a))}</li>`,V(s("No activity in the approvals workflow.",a));return}e.innerHTML=o.map(Ot).join(""),V(v(ln,n))}catch(r){if(I()!==n)return;e.innerHTML=`<li class="fp-approvals__placeholder fp-approvals__placeholder--error">${l(v(s("Unable to fetch the workflow (%s).",a),r.message))}</li>`,V(s("Unable to refresh the approvals workflow.",a))}}function Ft(e){var n;let t=E.suggestions[e];return`fp-mention-option-${(n=t==null?void 0:t.id)!=null?n:e}`}function ss(){E.anchor=-1,E.query="",E.suggestions=[],E.activeIndex=-1}function J(){let{list:e,textarea:t}=E;e&&(e.hidden=!0,e.innerHTML=""),t==null||t.setAttribute("aria-expanded","false"),t==null||t.removeAttribute("aria-activedescendant"),ee&&(window.clearTimeout(ee),ee=void 0),ss()}function je(){let{list:e,activeIndex:t,textarea:n}=E;if(!e)return;Array.from(e.querySelectorAll("[data-mention-index]")).forEach(o=>{var u;let c=Number((u=o.dataset.mentionIndex)!=null?u:"-1")===t;o.classList.toggle("is-active",c),o.setAttribute("aria-selected",c?"true":"false")}),n&&(t>=0&&E.suggestions[t]?n.setAttribute("aria-activedescendant",Ft(t)):n.removeAttribute("aria-activedescendant"))}function as(){let{list:e,suggestions:t,textarea:n,activeIndex:r}=E;if(e){if(!t.length){e.innerHTML=`<li class="fp-comments__mention fp-comments__mention--empty" role="option" aria-disabled="true">${l(s("No user found.",a))}</li>`,e.hidden=!1,n==null||n.setAttribute("aria-expanded","true"),n==null||n.removeAttribute("aria-activedescendant");return}e.innerHTML=t.map((o,i)=>{let c=o.description?`<span>${l(o.description)}</span>`:"";return`
        <li
          class="fp-comments__mention${r===i?" is-active":""}"
          data-mention-index="${i}"
          role="option"
          id="${Ft(i)}"
          aria-selected="${r===i?"true":"false"}"
        >
          <strong>${l(o.name)}</strong>
          ${c}
        </li>
      `}).join(""),e.hidden=!1,n==null||n.setAttribute("aria-expanded","true"),je()}}async function rs(e){let t=`/wp-json/wp/v2/users?per_page=5&search=${encodeURIComponent(e)}`,n=await fetch(t,{credentials:"same-origin",headers:{"X-WP-Nonce":T.nonce}});if(!n.ok)throw new Error(`HTTP ${n.status}`);return(await n.json()).map(o=>({id:o.id,name:o.name,slug:o.slug,description:o.description}))}async function os(e){let{list:t,textarea:n}=E;if(!t||!n)return;let r=++Ue;t.hidden=!1,t.innerHTML=`<li class="fp-comments__mention fp-comments__mention--loading" role="option" aria-disabled="true">${l(s("Searching users\u2026",a))}</li>`,n.setAttribute("aria-expanded","true");try{let o=await rs(e);if(r!==Ue)return;E.suggestions=o,E.activeIndex=o.length?0:-1,as(),o.length&&N(v(s("%d suggestions found.",a),o.length))}catch(o){if(r!==Ue)return;t.innerHTML=`<li class="fp-comments__mention fp-comments__mention--error" role="option" aria-disabled="true">${l(v(s("Error while searching (%s).",a),o.message))}</li>`,N(s("Unable to fetch mentions.",a))}}function jt(e){var f;let t=E.suggestions[e],n=E.textarea;if(!t||!n)return;let r=(f=n.selectionStart)!=null?f:n.value.length,o=n.value.slice(0,E.anchor),i=n.value.slice(r),u=`@${t.slug||t.name.replace(/\s+/g,"").toLowerCase()}`;n.value=`${o}${u} ${i.replace(/^\s*/,"")}`;let d=o.length+u.length+1;n.setSelectionRange(d,d),N(v(s("%s added to the comment.",a),t.name)),J()}function ls(e){var d;let t=e.currentTarget;E.textarea=t;let n=E.list;if(!n)return;let r=(d=t.selectionStart)!=null?d:t.value.length,i=t.value.slice(0,r),c=i.lastIndexOf("@");if(c===-1){J();return}if(c>0){let f=i.charAt(c-1);if(f&&/[\w@]/.test(f)){J();return}}let u=i.slice(c+1);if(!/^[\w._-]*$/.test(u)){J();return}if(E.anchor=c,u.length<2){E.query=u,E.suggestions=[],E.activeIndex=-1,n.hidden=!1,n.innerHTML=`<li class="fp-comments__mention fp-comments__mention--hint" role="option" aria-disabled="true">${l(s("Type at least two characters to search for a user.",a))}</li>`,t.setAttribute("aria-expanded","true"),t.removeAttribute("aria-activedescendant");return}u===E.query&&!n.hidden||(E.query=u,ee&&window.clearTimeout(ee),ee=window.setTimeout(()=>{os(u)},180))}function is(e){let{list:t,suggestions:n}=E;if(!(!t||t.hidden)){if(e.key==="ArrowDown"){if(!n.length)return;e.preventDefault(),E.activeIndex=(E.activeIndex+1)%n.length,je();return}if(e.key==="ArrowUp"){if(!n.length)return;e.preventDefault(),E.activeIndex=(E.activeIndex-1+n.length)%n.length,je();return}if(e.key==="Enter"||e.key==="Tab"){E.activeIndex>=0&&n[E.activeIndex]&&(e.preventDefault(),jt(E.activeIndex));return}e.key==="Escape"&&(e.preventDefault(),J())}}function cs(e,t){E.textarea=e,E.list=t,e.addEventListener("input",ls),e.addEventListener("keydown",is),e.addEventListener("blur",()=>{window.setTimeout(()=>{J()},120)}),t.addEventListener("mousedown",n=>{n.preventDefault()}),t.addEventListener("click",n=>{var i;let r=n.target.closest("[data-mention-index]");if(!r)return;let o=Number((i=r.dataset.mentionIndex)!=null?i:"-1");Number.isNaN(o)||jt(o)})}async function ds(){var c,u;let e=document.getElementById("fp-approvals-advance"),t=document.getElementById("fp-approvals-action-hint");if(!e)return;let n=I();if(n===null){V(ne);return}let r=Xe(),o=Bt(r);if(!o){V(Re);return}let i=(c=se[o])!=null?c:D(o);e.disabled=!0,e.setAttribute("aria-busy","true"),t&&(t.textContent="");try{let d=await P(`${T.restBase}/plans/${n}/status`,{method:"POST",body:JSON.stringify({status:o})}),f=typeof d.status=="string"&&d.status!==""?d.status:o;Pt(n,f),fe();let p=Array.isArray(d.approvals)?d.approvals:null;if(p){let h=document.getElementById("fp-approvals-timeline");h&&I()===n&&(p.length===0?h.innerHTML=`<li class="fp-approvals__placeholder">${l(s("No activity recorded in the workflow.",a))}</li>`:h.innerHTML=p.map(Ot).join(""))}else await Me();V(v(nn,i))}catch(d){let f=(u=d.message)!=null?u:s("Unknown error",a),p=v(an,f);t&&(t.textContent=p),V(p)}finally{e.disabled=!1,e.removeAttribute("aria-busy"),Ze()}}function ps(e,t,n){if(t.length===0){let i=n?v(s("No suggestions available for %s.",a),n):s("No suggestions available for the selected period.",a);e.innerHTML=`<p class="fp-besttime__empty">${l(i)}</p>`;return}let r=n?`<p class="fp-besttime__context">${l(v(s("Suggestions for %s",a),n))}</p>`:"",o=t.slice(0,6).map(i=>`
        <article class="fp-besttime__item">
          <h4>${new Date(i.datetime).toLocaleString()}</h4>
          <p>${i.reason}</p>
          <span class="fp-besttime__score">${l(v(s("Score %d",a),i.score))}</span>
        </article>
      `).join("");e.innerHTML=`${r}${o}`}async function Kt(e,t={}){var o;let n=new Headers((o=t.headers)!=null?o:{});n.has("Content-Type")||n.set("Content-Type","application/json"),n.has("X-WP-Nonce")||n.set("X-WP-Nonce",T.nonce);let r=await fetch(e,{credentials:"same-origin",...t,headers:n});if(!r.ok)throw new Error(`HTTP ${r.status}`);return r}async function P(e,t={}){return(await Kt(e,t)).json()}async function Ke(e){var o;let t=document.getElementById("fp-besttime-results");if(!t)return;t.innerHTML=`<p class="fp-besttime__loading">${l(s("Calculating suggestions\u2026",a))}</p>`;let n=new URLSearchParams({channel:j,month:Ye});T.brand&&n.set("brand",T.brand);let r;if(e){n.set("day",e);let i=new Date(e);Number.isNaN(i.getTime())||(r=At(i))}try{let i=await P(`${T.restBase}/besttime?${n.toString()}`);ps(t,i.suggestions,r)}catch(i){let c=(o=i==null?void 0:i.message)!=null?o:s("Unknown error",a);t.innerHTML=`<p class="fp-besttime__error">${l(v(s("Unable to fetch suggestions (%s).",a),c))}</p>`}}async function he(){let e=document.getElementById("fp-comments-list");if(!e)return;let t=I();if(t===null){e.innerHTML=`<p class="fp-comments__empty">${l(ke)}</p>`,N(ke);return}let n=t;e.innerHTML=`<p class="fp-comments__loading">${l(s("Loading comments\u2026",a))}</p>`;try{let r=await P(`${T.restBase}/plans/${n}/comments`);if(I()!==n)return;let o=Array.isArray(r.items)?r.items:[];if(!o.length){let i=v(dn,n);e.innerHTML=`<p class="fp-comments__empty">${l(i)}</p>`,N(i);return}e.innerHTML=o.map(i=>{let c=l(i.author.display_name),u=l(new Date(i.created_at).toLocaleString());return`
          <article class="fp-comments__item">
            <header>
              <strong>${c}</strong>
              <time>${u}</time>
            </header>
            <p>${kn(i.body)}</p>
          </article>
        `}).join(""),N(v(cn,n))}catch(r){if(I()!==n)return;e.innerHTML=`<p class="fp-comments__error">${l(v(s("Unable to load comments (%s).",a),r.message))}</p>`,N(s("Error while loading comments.",a))}}async function zt(){let e=document.getElementById("fp-shortlink-table"),t=document.getElementById("fp-shortlink-skeleton");if(!(!e||!t)){e.setAttribute("data-loading","true"),e.setAttribute("aria-busy","true"),t.removeAttribute("hidden"),x(m.shortlinks.feedback.loading,"muted");try{let n=await P(`${T.restBase}/links`);R=Array.isArray(n.items)?n.items:[],ze(),R.length===0?x(m.shortlinks.feedback.empty,"muted"):x(null)}catch(n){R=[],ze(),x(v(s("Unable to load links (%s).",a),n.message),"error")}finally{e.removeAttribute("data-loading"),e.setAttribute("aria-busy","false"),t.setAttribute("hidden","")}}}function x(e,t="muted"){let n=document.getElementById("fp-shortlink-feedback");if(n){if(!e){n.textContent="",n.setAttribute("hidden",""),n.removeAttribute("data-tone");return}n.textContent=e,n.dataset.tone=t,n.removeAttribute("hidden")}}function ze(){let e=document.getElementById("fp-shortlink-rows"),t=document.getElementById("fp-shortlink-empty"),n=document.getElementById("fp-shortlink-table");if(!e||!t||!n)return;if(R.length===0){e.innerHTML="",t.textContent=m.shortlinks.empty,t.removeAttribute("hidden"),n.setAttribute("data-empty","true");return}t.setAttribute("hidden",""),n.removeAttribute("data-empty");let r=new Intl.NumberFormat;e.innerHTML=R.map(o=>{let i=l(o.slug),c=l(o.target_url),u=l(wt(o.target_url)),d=l(me(o.slug)),f=r.format(Math.max(0,Number.isFinite(o.clicks)?o.clicks:0)),p=l(Ln(o.last_click_at)),h=_n("fp-shortlink-menu",o.slug),b=`${h}-toggle`,y=`${h}-panel`,g=l(v(m.shortlinks.menuLabel,o.slug)),L=l(m.shortlinks.actions.open),k=l(m.shortlinks.actions.copy),w=l(m.shortlinks.actions.edit),S=l(m.shortlinks.actions.disable);return`
        <tr data-slug="${i}">
          <th scope="row"><code class="fp-shortlink__slug">${i}</code></th>
          <td><span class="fp-shortlink__target" title="${c}">${u}</span></td>
          <td class="fp-shortlink__metric">${f}</td>
          <td class="fp-shortlink__metric">${p}</td>
          <td class="fp-shortlink__actions">
            <div class="fp-shortlink__menu">
              <button
                type="button"
                class="fp-shortlink__menu-toggle"
                id="${b}"
                data-shortlink-menu
                data-slug="${i}"
                data-url="${d}"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="${y}"
              >
                <span class="screen-reader-text">${g}</span>
                <span aria-hidden="true" class="fp-shortlink__menu-icon">\u22EE</span>
              </button>
              <div class="fp-shortlink__menu-panel" role="menu" id="${y}" aria-labelledby="${b}" hidden>
                <button type="button" role="menuitem" data-shortlink-action="open" data-slug="${i}" data-url="${d}" data-target="${c}">${L}</button>
                <button type="button" role="menuitem" data-shortlink-action="copy" data-slug="${i}" data-url="${d}">${k}</button>
                <button type="button" role="menuitem" data-shortlink-action="edit" data-slug="${i}">${w}</button>
                <button type="button" role="menuitem" data-shortlink-action="disable" data-slug="${i}">${S}</button>
              </div>
            </div>
          </td>
        </tr>
      `}).join("")}function us(e){return Array.from(e.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])')).filter(t=>t.offsetParent!==null)}function de(){if(!U)return;let e=U.nextElementSibling;U.classList.remove("is-open"),U.setAttribute("aria-expanded","false"),e==null||e.setAttribute("hidden",""),U=null}function ms(e){if(U===e){de();return}de();let t=e.nextElementSibling;if(!t)return;e.classList.add("is-open"),e.setAttribute("aria-expanded","true"),t.removeAttribute("hidden"),U=e;let n=t.querySelector('[role="menuitem"]');n==null||n.focus()}async function fs(e){var t;try{if((t=navigator.clipboard)!=null&&t.writeText)return await navigator.clipboard.writeText(e),!0}catch(n){console.warn("Clipboard API non disponibile",n)}try{let n=document.createElement("textarea");n.value=e,n.setAttribute("readonly","true"),n.style.position="absolute",n.style.left="-9999px",document.body.appendChild(n),n.select();let r=document.execCommand("copy");return document.body.removeChild(n),r}catch(n){return console.warn("Fallback clipboard copy fallito",n),!1}}async function hs(e){var r,o,i;let t=e.dataset.shortlinkAction,n=(r=e.dataset.slug)!=null?r:"";if(!(!t||!n)){if(t==="open"){let c=(o=e.dataset.url)!=null?o:me(n),u=window.open(c,"_blank","noopener");u&&(u.opener=null),x(v(m.shortlinks.feedback.open,n),"success");return}if(t==="copy"){let c=(i=e.dataset.url)!=null?i:me(n);await fs(c)?x(m.shortlinks.feedback.copySuccess,"success"):x(m.shortlinks.feedback.copyError,"error");return}if(t==="edit"){let c=R.find(u=>u.slug===n);Vt("edit",c);return}t==="disable"&&await gs(n)}}async function gs(e){if(e){x(m.shortlinks.feedback.disabling,"muted");try{await Kt(`${T.restBase}/links/${encodeURIComponent(e)}`,{method:"DELETE"}),R=R.filter(t=>t.slug!==e),ze(),R.length===0?x(m.shortlinks.feedback.disabledEmpty,"success"):x(m.shortlinks.feedback.disabled,"success")}catch(t){x(v(m.shortlinks.errors.disable,t.message),"error")}}}function ge(){let e=document.getElementById("fp-shortlink-modal");if(!(e instanceof HTMLElement))return null;let t=e.querySelector("#fp-shortlink-modal-form"),n=e.querySelector("#fp-shortlink-modal-title"),r=e.querySelector("#fp-shortlink-input-slug"),o=e.querySelector("#fp-shortlink-input-target"),i=e.querySelector("#fp-shortlink-modal-preview"),c=e.querySelector("#fp-shortlink-modal-error"),u=e.querySelector("#fp-shortlink-modal-submit"),d=e.querySelector("#fp-shortlink-modal-cancel"),f=e.querySelector("[data-shortlink-modal-close]"),p=e.querySelector("[data-shortlink-modal-overlay]");return!t||!n||!r||!o||!i||!c||!u||!d||!f||!p?null:{modal:e,form:t,title:n,slugInput:r,targetInput:o,preview:i,error:c,submit:u,cancel:d,close:f,overlay:p}}function Ie(){let e=ge();if(!e)return;let{slugInput:t,targetInput:n,preview:r,error:o,submit:i}=e,c=t.value.trim(),u=n.value.trim(),d=[];c?/^[a-z0-9-]+$/i.test(c)||d.push(m.shortlinks.validation.slugFormat):d.push(m.shortlinks.validation.slugMissing);let f=null;if(!u)d.push(m.shortlinks.validation.targetMissing);else try{f=new URL(u)}catch{d.push(m.shortlinks.validation.targetInvalid)}let p="";if(f){let w=new URL(f.toString());w.searchParams.set("utm_source","fp-publisher"),w.searchParams.set("utm_medium","social"),w.searchParams.set("utm_campaign",c||"shortlink"),p=w.toString()}d.length>0?(o.textContent=d.join(" "),o.removeAttribute("hidden"),i.disabled=!0):(o.textContent="",o.setAttribute("hidden",""),i.disabled=!1);let h=me(c||"preview"),b=l(m.shortlinks.preview.shortlinkLabel),y=l(m.shortlinks.preview.utmLabel),g=l(m.shortlinks.preview.waiting),L=l(m.shortlinks.modal.previewDefault),k=[`<p><strong>${b}</strong> <code>${l(h)}</code></p>`];p?k.push(`<p><strong>${y}</strong> <span title="${l(p)}">${l(wt(p,96))}</span></p>`):u?k.push(`<p>${g}</p>`):k.push(`<p>${L}</p>`),r.innerHTML=k.join("")}function pe(){let e=ge();if(!e)return;let{modal:t,form:n,error:r,preview:o}=e;t.setAttribute("hidden",""),t.classList.remove("is-open"),Le&&(t.removeEventListener("keydown",Le),Le=null),n.reset(),r.textContent="",r.setAttribute("hidden",""),o.innerHTML=`<p>${l(m.shortlinks.modal.previewDefault)}</p>`,_e&&_e.focus(),_e=null,oe=null}function Vt(e,t){var f,p,h,b;let n=ge();if(!n)return;let{modal:r,title:o,slugInput:i,targetInput:c,submit:u}=n;_e=(f=document.activeElement)!=null?f:null,r.dataset.mode=e,o.textContent=e==="edit"?m.shortlinks.modal.editTitle:m.shortlinks.modal.createTitle,u.textContent=e==="edit"?m.shortlinks.modal.update:m.shortlinks.modal.create,i.value=(p=t==null?void 0:t.slug)!=null?p:"",c.value=(h=t==null?void 0:t.target_url)!=null?h:"",oe=(b=t==null?void 0:t.slug)!=null?b:null,Ie(),r.removeAttribute("hidden"),r.classList.add("is-open");let d=y=>{if(y.key==="Escape"){y.preventDefault(),pe();return}if(y.key==="Tab"){let g=us(r);if(g.length===0)return;let L=g[0],k=g[g.length-1];y.shiftKey?document.activeElement===L&&(y.preventDefault(),k.focus()):document.activeElement===k&&(y.preventDefault(),L.focus())}};Le=d,r.addEventListener("keydown",d),i.focus()}async function bs(e){e.preventDefault();let t=ge();if(!t)return;let{modal:n,slugInput:r,targetInput:o,error:i,submit:c}=t;if(Ie(),c.disabled)return;let u=r.value.trim(),d=o.value.trim(),f=n.dataset.mode==="edit"?"edit":"create";c.disabled=!0,c.setAttribute("aria-busy","true");try{let p={slug:u,target_url:d};if(f==="edit"){let h=`${T.restBase}/links/${encodeURIComponent(oe!=null?oe:u)}`;await P(h,{method:"PUT",body:JSON.stringify(p)}),x(m.shortlinks.feedback.updated,"success")}else await P(`${T.restBase}/links`,{method:"POST",body:JSON.stringify(p)}),x(m.shortlinks.feedback.created,"success");await zt(),pe()}catch(p){i.textContent=v(m.shortlinks.errors.save,p.message),i.removeAttribute("hidden")}finally{c.disabled=!1,c.removeAttribute("aria-busy")}}function vs(){var f;let e=document.getElementById("fp-besttime-trigger");e==null||e.addEventListener("click",()=>{Ke()});let t=document.getElementById("fp-calendar-toolbar");t==null||t.addEventListener("click",p=>{let h=p.target.closest("[data-calendar-density]");if(!h)return;p.preventDefault();let b=h.dataset.calendarDensity==="compact"?"compact":"comfort";Wn(b)});let n=document.getElementById("fp-calendar");n==null||n.addEventListener("click",p=>{var L;let h=p.target,b=h.closest(".fp-calendar__slot-action");if(b){p.preventDefault(),Gn(b,(L=b.dataset.date)!=null?L:"");return}let y=h.closest(".fp-calendar__item[data-plan-id]");if(y){let k=re(y.getAttribute("data-plan-id"));k!==null&&(p.preventDefault(),Q(k));return}let g=h.closest('[data-action="calendar-import"]');g&&(p.preventDefault(),Yn(g))}),n==null||n.addEventListener("keydown",p=>{let h=p.target.closest(".fp-calendar__item[data-plan-id]");if(h&&(p.key==="Enter"||p.key===" "||p.key==="Spacebar")){p.preventDefault();let b=re(h.getAttribute("data-plan-id"));b!==null&&Q(b)}});let r=document.querySelector(".fp-kanban");r==null||r.addEventListener("click",p=>{var g;let h=p.target;if(h.closest('[data-action="besttime"]')){p.preventDefault(),(g=document.getElementById("fp-besttime-section"))==null||g.scrollIntoView({behavior:"smooth"}),Ke();return}let y=h.closest(".fp-kanban-card[data-plan-id]");if(y){let L=re(y.getAttribute("data-plan-id"));L!==null&&(p.preventDefault(),Q(L))}}),r==null||r.addEventListener("keydown",p=>{let h=p.target.closest(".fp-kanban-card[data-plan-id]");if(h&&(p.key==="Enter"||p.key===" "||p.key==="Spacebar")){p.preventDefault();let b=re(h.getAttribute("data-plan-id"));b!==null&&Q(b)}});let o=document.getElementById("fp-approvals-advance");o==null||o.addEventListener("click",p=>{p.preventDefault(),ds()}),(f=document.getElementById("fp-refresh-comments"))==null||f.addEventListener("click",()=>{he()});let i=document.getElementById("fp-comments-form");if(i==null||i.addEventListener("submit",async p=>{p.preventDefault();let h=i.querySelector("textarea"),b=i.querySelector('button[type="submit"]');if(!h||!b)return;let y=h.value.trim();if(!y){N(s("Fill the comment before sending.",a));return}let g=I();if(g===null){N(ke);return}b.disabled=!0,b.setAttribute("aria-busy","true");try{await P(`${T.restBase}/plans/${g}/comments`,{method:"POST",body:JSON.stringify({body:y})}),h.value="",J(),N(v(pn,g)),await he()}catch(L){let k=document.getElementById("fp-comments-list");k&&(k.innerHTML=`<p class="fp-comments__error">${l(v(s("Error while sending (%s).",a),L.message))}</p>`),N(v(s("Unable to send the comment (%s).",a),L.message))}finally{b.disabled=!1,b.removeAttribute("aria-busy")}}),i){let p=i.querySelector("textarea"),h=document.getElementById("fp-mentions-list");p instanceof HTMLTextAreaElement&&h instanceof HTMLUListElement&&cs(p,h)}let c=document.getElementById("fp-shortlink"),u=document.getElementById("fp-shortlink-create");u instanceof HTMLButtonElement&&u.addEventListener("click",p=>{p.preventDefault(),Vt("create")}),c instanceof HTMLElement&&c.addEventListener("click",p=>{let h=p.target,b=h.closest("[data-shortlink-menu]");if(b){p.preventDefault(),ms(b);return}let y=h.closest("[data-shortlink-action]");y&&(p.preventDefault(),de(),hs(y))}),document.addEventListener("click",p=>{let h=p.target;U&&!h.closest(".fp-shortlink__menu")&&de()}),document.addEventListener("keydown",p=>{if(p.key==="Escape"&&U){let h=U;de(),h.focus()}});let d=ge();if(d){let{form:p,slugInput:h,targetInput:b,cancel:y,close:g,overlay:L}=d;p.addEventListener("submit",k=>{bs(k)}),h.addEventListener("input",Ie),b.addEventListener("input",Ie),y.addEventListener("click",k=>{k.preventDefault(),pe()}),g.addEventListener("click",k=>{k.preventDefault(),pe()}),L.addEventListener("click",k=>{k.preventDefault(),pe()})}Ut()}function ys(e,t){var d;e.classList.remove("is-loading"),e.classList.add("is-ready"),e.innerHTML=`
    <main class="fp-publisher-shell">
      <header class="fp-publisher-shell__header">
        <div>
          <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
          <p class="fp-publisher-shell__subtitle">${l(s("Planning workflow & time suggestions",a))}</p>
        </div>
        <span class="fp-publisher-shell__version">v${(d=t.version)!=null?d:T.version}</span>
      </header>

      <section class="fp-publisher-shell__grid">
        <article class="fp-widget">
          <header class="fp-widget__header">
            <div class="fp-widget__heading">
              <h2>${l(s("Editorial calendar",a))}</h2>
              <span>${Ye}</span>
            </div>
            <div
              class="fp-calendar__toolbar"
              id="fp-calendar-toolbar"
              role="group"
              aria-label="${l(s("Calendar density",a))}"
            >
              <button
                type="button"
                class="fp-calendar__density-button is-active"
                data-calendar-density="comfort"
                aria-pressed="true"
                aria-controls="fp-calendar"
              >${l(s("Comfort",a))}</button>
              <button
                type="button"
                class="fp-calendar__density-button"
                data-calendar-density="compact"
                aria-pressed="false"
                aria-controls="fp-calendar"
              >${l(s("Compact",a))}</button>
            </div>
          </header>
          <div id="fp-calendar"></div>
        </article>

        <article class="fp-widget fp-kanban" aria-live="polite">
          <header class="fp-widget__header">
            <h2>${l(s("Scheduling status",a))}</h2>
            <span>${l(s("Drag & drop (demo)",a))}</span>
          </header>
          <div id="fp-kanban"></div>
        </article>

        <article class="fp-widget" id="fp-besttime-section">
          <header class="fp-widget__header">
            <h2>${l(s("Best time to publish",a))}</h2>
            <button type="button" class="button" id="fp-besttime-trigger">${l(s("Suggest time",a))}</button>
          </header>
          <div id="fp-besttime-results" class="fp-besttime"></div>
        </article>

        <article class="fp-widget fp-composer" id="fp-composer"></article>

        <article class="fp-widget">
          <div id="fp-comments"></div>
        </article>

        <article class="fp-widget" id="fp-alerts"></article>

        <article class="fp-widget" id="fp-logs"></article>

        <article class="fp-widget" id="fp-shortlink">
          <header class="fp-widget__header">
            <div class="fp-widget__heading">
              <h2 id="fp-shortlink-title">${l(m.shortlinks.section.title)}</h2>
              <span>${l(m.shortlinks.section.subtitle)}</span>
            </div>
            <button type="button" class="button button-primary" id="fp-shortlink-create">${l(m.shortlinks.section.createButton)}</button>
          </header>
          <div class="fp-shortlink__body">
            <p id="fp-shortlink-feedback" class="fp-shortlink__feedback" aria-live="polite" hidden></p>
            <div
              class="fp-shortlink__table"
              id="fp-shortlink-table"
              role="region"
              aria-labelledby="fp-shortlink-title"
              aria-live="polite"
            >
              <table>
                <thead>
                  <tr>
                    <th scope="col">${l(m.shortlinks.table.slug)}</th>
                    <th scope="col">${l(m.shortlinks.table.target)}</th>
                    <th scope="col">${l(m.shortlinks.table.clicks)}</th>
                    <th scope="col">${l(m.shortlinks.table.lastClick)}</th>
                    <th scope="col" aria-label="${l(m.shortlinks.table.actions)}">\u22EF</th>
                  </tr>
                </thead>
                <tbody id="fp-shortlink-rows"></tbody>
              </table>
              <div id="fp-shortlink-skeleton" class="fp-shortlink__skeleton" aria-hidden="true" hidden>
                <div class="fp-shortlink__skeleton-row"></div>
                <div class="fp-shortlink__skeleton-row"></div>
                <div class="fp-shortlink__skeleton-row"></div>
              </div>
            </div>
            <p id="fp-shortlink-empty" class="fp-shortlink__empty" hidden>
              ${l(m.shortlinks.empty)}
            </p>
          </div>
          <div
            class="fp-modal"
            id="fp-shortlink-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="fp-shortlink-modal-title"
            hidden
          >
            <div class="fp-modal__backdrop" data-shortlink-modal-overlay></div>
            <div class="fp-modal__dialog" role="document">
              <header class="fp-modal__header">
                <h2 id="fp-shortlink-modal-title">${l(m.shortlinks.modal.createTitle)}</h2>
                <button
                  type="button"
                  class="fp-modal__close"
                  data-shortlink-modal-close
                  aria-label="${l(m.common.close)}"
                >\xD7</button>
              </header>
              <form id="fp-shortlink-modal-form" class="fp-shortlink__form" novalidate>
                <label class="fp-shortlink__field">
                  <span>${l(m.shortlinks.modal.slugLabel)}</span>
                  <input
                    type="text"
                    id="fp-shortlink-input-slug"
                    name="slug"
                    autocomplete="off"
                    required
                    placeholder="${l(m.shortlinks.modal.slugPlaceholder)}"
                  />
                </label>
                <label class="fp-shortlink__field">
                  <span>${l(m.shortlinks.modal.targetLabel)}</span>
                  <input
                    type="url"
                    id="fp-shortlink-input-target"
                    name="target_url"
                    required
                    placeholder="${l(m.shortlinks.modal.targetPlaceholder)}"
                  />
                </label>
                <div id="fp-shortlink-modal-preview" class="fp-shortlink__preview" aria-live="polite">
                  <p>${l(m.shortlinks.modal.previewDefault)}</p>
                </div>
                <p id="fp-shortlink-modal-error" class="fp-shortlink__error" role="alert" hidden></p>
                <footer class="fp-modal__footer">
                  <button type="button" class="button" id="fp-shortlink-modal-cancel">${l(m.shortlinks.modal.cancel)}</button>
                  <button type="submit" class="button button-primary" id="fp-shortlink-modal-submit">${l(m.shortlinks.modal.create)}</button>
                </footer>
              </form>
            </div>
          </div>
        </article>
      </section>
    </main>
  `;let n=document.getElementById("fp-calendar");n&&Nt(n);let r=document.getElementById("fp-kanban");r&&(es(r),xt());let o=document.getElementById("fp-composer");o&&(On(o),Fn());let i=document.getElementById("fp-comments");i&&(ts(i),he(),Me());let c=document.getElementById("fp-alerts");c&&xn(c);let u=document.getElementById("fp-logs");u&&qn(u),zt(),vs(),fe(),Ze()}async function _s(){if(Y&&(Y.classList.add("fp-publisher-admin__mount","is-loading"),Y.innerHTML=`
    <main class="fp-publisher-shell">
      <header class="fp-publisher-shell__header">
        <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
        <span class="fp-publisher-shell__version">v${T.version}</span>
      </header>
      <section class="fp-publisher-shell__content">
        <p class="fp-publisher-shell__message">${l(s("Loading application status\u2026",a))}</p>
      </section>
    </main>
  `,!(!T.restBase||!T.nonce)))try{let e=await P(`${T.restBase}/status`);ys(Y,e)}catch(e){Y.classList.remove("is-loading"),Y.classList.add("has-error"),Y.innerHTML=`
      <main class="fp-publisher-shell">
        <header class="fp-publisher-shell__header">
          <h1 class="fp-publisher-shell__title">FP Digital Publisher</h1>
        </header>
        <section class="fp-publisher-shell__content">
          <p class="fp-publisher-shell__message">${l(v(s("Error while fetching the status: %s",a),e.message))}</p>
        </section>
      </main>
    `}}_s();})();
//# sourceMappingURL=index.js.map
