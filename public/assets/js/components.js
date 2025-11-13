(function(){
    const Modal = {
        _el:null,_body:null,_actions:null,
        ensure(){ if(this._el) return; const el=document.createElement('div'); el.id='ui-modal'; el.className='fixed inset-0 hidden z-[9999] bg-black/50 flex items-center justify-center p-4'; el.innerHTML=`<div class="bg-white rounded-xl shadow-xl max-w-md w-full overflow-hidden"><div class="p-6" id="ui-modal-body"></div><div class="p-4 border-t border-gray-200 flex items-center justify-end gap-2" id="ui-modal-actions"></div></div>`; document.body.appendChild(el); this._el=el; this._body=el.querySelector('#ui-modal-body'); this._actions=el.querySelector('#ui-modal-actions'); el.addEventListener('click',e=>{ if(e.target===el) this.close(); }); },
        open({html,actions}){ this.ensure(); this._body.innerHTML=html; this._actions.innerHTML=''; (actions||[]).forEach(a=>{ const btn=document.createElement('button'); btn.className = a.variant==='danger'? 'px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700':'px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black'; btn.textContent=a.text||'OK'; btn.addEventListener('click',()=>{ if(a.onClick) a.onClick(); if(a.autoClose!==false) this.close(); }); this._actions.appendChild(btn); }); this._el.classList.remove('hidden'); },
        close(){ if(this._el) this._el.classList.add('hidden'); },
        alert(msg,title='Thông báo'){ this.open({html:`<h3 class='text-lg font-semibold mb-2'>${title}</h3><p>${msg}</p>`,actions:[{text:'OK'}]}); },
        confirm(msg,onConfirm,opt={}){ this.open({html:`<h3 class='text-lg font-semibold mb-2'>${opt.title||'Xác nhận'}</h3><p>${msg}</p>`,actions:[{text:opt.cancelText||'Hủy',variant:'secondary'},{text:opt.confirmText||'Xác nhận',variant:'primary',onClick:onConfirm}]}); }
    };
    window.UI = { Modal };
})();





