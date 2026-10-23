/* LSL Financial Portal · accessible interaction helpers. Server validates every action. */
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('[data-menu]');
  const sidebar = document.querySelector('.sidebar');
  const backdrop = document.querySelector('.menu-backdrop');
  const closeButtons = document.querySelectorAll('[data-menu-close]');
  const setOpen = (isOpen) => {
    if (!sidebar) return;
    sidebar.classList.toggle('open', isOpen);
    backdrop?.classList.toggle('visible', isOpen);
    toggle?.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('nav-open', isOpen);
    if (isOpen) sidebar.querySelector('.sidebar-close')?.focus();
    else if (document.activeElement?.closest('.sidebar')) toggle?.focus();
  };
  toggle?.addEventListener('click', () => setOpen(!sidebar?.classList.contains('open')));
  closeButtons.forEach(button => button.addEventListener('click', () => setOpen(false)));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && sidebar?.classList.contains('open')) setOpen(false);
  });
  window.matchMedia('(min-width: 851px)').addEventListener('change', event => {
    if (event.matches) setOpen(false);
  });

  document.querySelectorAll('[data-toggle-password]').forEach(button => {
    const input = document.getElementById(button.dataset.togglePassword);
    if (!input) return;
    button.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  const transactionType = document.getElementById('transaction-type');
  if (transactionType) {
    const update = () => {
      const expense = transactionType.value === 'expense';
      document.querySelectorAll('.expense-only').forEach(element => element.classList.toggle('hidden', !expense));
      const receipt = document.querySelector('input[name="receipt"]');
      const budgetLine = document.querySelector('select[name="budget_line_id"]');
      const category = document.querySelector('input[name="category"]');
      if (receipt) receipt.required = expense;
      if (budgetLine) budgetLine.required = expense;
      if (category) {
        category.required = !expense;
        category.closest('label')?.classList.toggle('hidden', expense);
      }
    };
    transactionType.addEventListener('change', update);
    update();
  }

  document.querySelectorAll('.inline-decision').forEach(form => {
    const decision = form.querySelector('select[name="decision"]');
    const reason = form.querySelector('input[name="reason"]');
    if (!decision || !reason) return;
    const update = () => { reason.required = ['rejected', 'returned'].includes(decision.value); };
    decision.addEventListener('change', update);
    update();
  });
});

// Progressive enhancement for Release 2: date/payee/description/category/amount
// are serialized into the already-validated server-side legacy line format.
document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('[data-liq-editor]');
  if (!editor) return;
  const form = editor.closest('form');
  const template = document.getElementById('liquidation-row-template');
  const rows = editor.querySelector('[data-liq-rows]');
  const textArea = form.querySelector('[data-liq-items]');
  const manualEvidence = form.querySelector('[data-manual-evidence]');
  const requestSelect = form.querySelector('select[name="request_id"]');
  if (!template || !rows || !textArea || !manualEvidence || !requestSelect) return;
  editor.hidden = false;
  textArea.closest('label').classList.add('hidden');
  textArea.required = false;
  manualEvidence.classList.add('hidden');
  manualEvidence.querySelector('input[type=file]').disabled = true;
  manualEvidence.querySelector('input[type=file]').required = false;

  const update = () => {
    let sum = 0;
    const budgetId = requestSelect.selectedOptions[0]?.dataset.budget || '';
    const all = [...rows.querySelectorAll('.liquidation-row')];
    all.forEach((row, index) => {
      row.querySelector('.line-number').textContent = `Expense item ${index + 1}`;
      row.querySelector('[data-liq-remove]').disabled = all.length === 1;
      const line = row.querySelector('[data-field=line]');
      [...line.options].forEach(option => {
        if (!option.value) return;
        option.disabled = option.dataset.budget !== budgetId;
        option.hidden = option.disabled;
      });
      if (line.selectedOptions[0]?.disabled) line.value = '';
      sum += Math.round(Math.max(0, Number(row.querySelector('[data-field=amount]').value) || 0) * 100);
    });
    editor.querySelector('[data-liq-total]').textContent = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(sum / 100);
    editor.querySelector('[data-liq-count]').textContent = `${all.length} item${all.length === 1 ? '' : 's'}`;
  };
  const add = () => {
    if (rows.children.length >= 40) return;
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.liquidation-row');
    const now = new Date();
    row.querySelector('[data-field=date]').value = [now.getFullYear(), String(now.getMonth()+1).padStart(2,'0'), String(now.getDate()).padStart(2,'0')].join('-');
    row.querySelector('[data-liq-remove]').addEventListener('click', () => { row.remove(); update(); });
    row.addEventListener('input', update);
    row.addEventListener('change', update);
    rows.appendChild(clone);
    update();
  };
  editor.querySelector('[data-liq-add]').addEventListener('click', add);
  requestSelect.addEventListener('change', update);
  form.addEventListener('submit', event => {
    const lines = [];
    for (const row of rows.querySelectorAll('.liquidation-row')) {
      const get = name => row.querySelector(`[data-field="${name}"]`).value.trim();
      const values = ['date','payee','description','line','amount'].map(get);
      // | or newline is the parser delimiter; refuse it instead of corrupting a line.
      if (values.some(value => value.includes('|') || /[\r\n]/.test(value))) {
        event.preventDefault();
        window.alert('Please remove | and line breaks from expense-item fields.');
        return;
      }
      lines.push(values.join('|'));
    }
    textArea.value = lines.join('\n');
  });
  add();
});

document.addEventListener('DOMContentLoaded', () => {
  const editor = document.querySelector('[data-budget-editor]');
  if (!editor) return;
  const form = editor.closest('form');
  const template = document.getElementById('budget-row-template');
  const rows = editor.querySelector('[data-budget-rows]');
  const textarea = form.querySelector('[data-budget-lines]');
  if (!template || !rows || !textarea) return;
  editor.hidden = false;
  textarea.closest('label').classList.add('hidden');
  textarea.required = false;
  const update = () => {
    const all = [...rows.querySelectorAll('.liquidation-row')];
    let sum = 0;
    all.forEach((row, index) => {
      row.querySelector('.line-number').textContent = `Category ${index+1}`;
      row.querySelector('[data-budget-remove]').disabled = all.length === 1;
      sum += Math.round(Math.max(0, Number(row.querySelector('[data-field=amount]').value) || 0) * 100);
    });
    editor.querySelector('[data-budget-count]').textContent = `${all.length} categor${all.length === 1 ? 'y' : 'ies'}`;
    editor.querySelector('[data-budget-total]').textContent = new Intl.NumberFormat('en-PH', {style:'currency', currency:'PHP'}).format(sum/100);
  };
  const add = () => {
    if (rows.children.length >= 40) return;
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.liquidation-row');
    row.querySelector('[data-budget-remove]').addEventListener('click', () => {row.remove();update();});
    row.addEventListener('input',update);
    rows.appendChild(clone);
    update();
  };
  editor.querySelector('[data-budget-add]').addEventListener('click',add);
  form.addEventListener('submit',event=>{
    const lines=[];
    for(const row of rows.querySelectorAll('.liquidation-row')){
      const category=row.querySelector('[data-field=category]').value.trim();
      const amount=row.querySelector('[data-field=amount]').value.trim();
      if(category.includes('|') || /[\r\n]/.test(category)){
        event.preventDefault();window.alert('Category names cannot contain | or line breaks.');return;
      }
      lines.push(category+'|'+amount);
    }
    textarea.value=lines.join('\n');
  });
  add();
});
