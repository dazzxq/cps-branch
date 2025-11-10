(function(){
    function confirm(message, onConfirm, opt){ UI.Modal.confirm(message, onConfirm, opt||{}); }
    async function api(url, opts){ const res = await fetch(url, Object.assign({ headers: { 'Content-Type':'application/json' }}, opts||{})); const data = await res.json().catch(()=>({})); if(!res.ok || data.success===false){ throw new Error(data.error||('HTTP '+res.status)); } return data; }
    window.App = { confirm, api };
})();




