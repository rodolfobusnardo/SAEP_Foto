function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.error('Toast container not found!');
        return;
    }

    const toast = document.createElement('div');
    toast.className = `toast-message ${type === 'success' ? 'success-message' : 'error-message'}`;
    toast.style.padding = '15px';
    toast.style.marginBottom = '10px';
    toast.style.borderRadius = '5px';
    toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    toast.style.transform = 'translateY(-20px)';

    toast.textContent = message;

    toastContainer.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
    }, 10);

    // Animate out and remove after 5 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-20px)';
        toast.addEventListener('transitionend', () => {
            toast.remove();
        });
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    // --- Variáveis de Estado Globais ---
    let allFilteredSelectedIds = [];
    let isSelectAllFilteredActive = false;
    let totalFilteredItemsCount = 0; 

    // --- Seletores de Elementos ---
    const filterForm = document.getElementById('filterForm');
    const itemListContainer = document.getElementById('itemListContainer');
    const clearFiltersButton = document.getElementById('clearFiltersButton');
    const selectFilteredCheckbox = document.getElementById('selectFilteredCheckbox');
    const devolverButton = document.getElementById('devolverButton');
    const doarButton = document.getElementById('doarButton');
    const descartarButton = document.getElementById('descartarButton');
    const imprimirCodBarrasButton = document.getElementById('imprimirCodBarrasButton');
    const itemNameInput = document.getElementById('filter_item_name');
    const itemCountContainer = document.getElementById('item-count-container');
    
    // =================================================================================
    // LÓGICA PARA O TOOLTIP GLOBAL COM JAVASCRIPT
    // =================================================================================
    function initializeTooltips(container = document) {
        const globalTooltip = document.getElementById('global-tooltip');
        if (!globalTooltip) return;

        const showTooltip = (target) => {
            const tooltipText = target.getAttribute('data-tooltip');
            if (tooltipText) {
                globalTooltip.innerHTML = tooltipText;
                const rect = target.getBoundingClientRect();
                globalTooltip.style.display = 'block';
                const tooltipRect = globalTooltip.getBoundingClientRect();

                let top = rect.top - tooltipRect.height - 10;
                let left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);

                if (left < 5) left = 5;
                if ((left + tooltipRect.width) > window.innerWidth) {
                    left = window.innerWidth - tooltipRect.width - 5;
                }
                if (top < 5) top = rect.bottom + 10;
                
                globalTooltip.style.top = `${top}px`;
                globalTooltip.style.left = `${left}px`;
                globalTooltip.style.opacity = '1';
            }
        };

        const hideTooltip = () => {
            globalTooltip.style.opacity = '0';
        };

        container.addEventListener('mouseover', (e) => {
            if (e.target && typeof e.target.closest === 'function') {
                const target = e.target.closest('[data-tooltip]');
                if (target) {
                    showTooltip(target);
                }
            }
        });

        container.addEventListener('mouseout', (e) => {
             if (e.target && typeof e.target.closest === 'function') {
                const target = e.target.closest('[data-tooltip]');
                if (target) {
                    hideTooltip();
                }
            }
        });
    }
    
    // --- Funções Auxiliares ---
    function escapeHTML(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, match => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[match]));
    }

    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    function renderBarcodes(items, isAjax) {
        items.forEach(item => {
            if (item.barcode) {
                try {
                    const prefix = isAjax ? "barcode-ajax-" : "barcode-";
                    const barcodeElement = document.getElementById(prefix + item.id);
                    if (barcodeElement) {
                        JsBarcode(barcodeElement, item.barcode, {
                            format: "CODE128", lineColor: "#000", width: 1.5, height: 40,
                            displayValue: true, fontSize: 12, margin: 5
                        });
                    }
                } catch (e) {
                    console.error(`Erro ao gerar barcode para o item ID ${item.id}:`, e);
                }
            }
        });
    }
    
    // --- Lógica de Atualização da Interface ---
    function updateSelectFilteredCheckboxState() {
        if (!selectFilteredCheckbox) return;
        const wrapper = selectFilteredCheckbox.closest('.tooltip-wrapper');
        if (!wrapper) return;

        let hasActiveFilter = false;
        if (filterForm) {
            const formData = new FormData(filterForm);
            for (const value of formData.values()) {
                if (value && String(value).trim() !== '') {
                    hasActiveFilter = true;
                    break;
                }
            }
        }

        selectFilteredCheckbox.disabled = !hasActiveFilter;

        if (hasActiveFilter) {
            wrapper.removeAttribute('data-tooltip');
        } else {
            wrapper.setAttribute('data-tooltip', 'Para fazer uso desta funcionalidade, primeiro aplique algum filtro.<br>(Por exemplo: selecione e aplique o filtro \'Pendente\').');
        }
    }

    function updateActionButtonsState() {
        if (!devolverButton || !doarButton || !imprimirCodBarrasButton || !descartarButton) return;
        
        const selectedVisibleCheckboxes = document.querySelectorAll('#itemListContainer .item-checkbox:checked');
        const anySelected = isSelectAllFilteredActive || selectedVisibleCheckboxes.length > 0;

        devolverButton.disabled = !anySelected;
        doarButton.disabled = !anySelected;
        descartarButton.disabled = !anySelected;

        let anySelectedWithBarcode = false;
        if (anySelected) {
             selectedVisibleCheckboxes.forEach(checkbox => {
                if (checkbox.closest('tr').querySelector('.barcode-image')) {
                    anySelectedWithBarcode = true;
                }
            });
        }
        imprimirCodBarrasButton.disabled = !(isSelectAllFilteredActive || anySelectedWithBarcode);
    }
    
    // --- Manipuladores de Eventos ---
    function handleItemCheckboxChange() {
        if (isSelectAllFilteredActive) {
            isSelectAllFilteredActive = false;
            allFilteredSelectedIds = [];
            if(selectFilteredCheckbox) selectFilteredCheckbox.checked = false;
            document.querySelectorAll('#itemListContainer .item-checkbox').forEach(cb => cb.disabled = false);
        }
        updateActionButtonsState();
    }
    
    function handleSelectFilteredChange() {
        if (!selectFilteredCheckbox) return;

        const isChecking = selectFilteredCheckbox.checked;

        if (isChecking) {
            if (!confirm(`Tem certeza que deseja selecionar todos os ${totalFilteredItemsCount} itens retornados pelo filtro? Esta ação não pode ser desfeita.`)) {
                selectFilteredCheckbox.checked = false;
                return;
            }

            const currentParams = new URLSearchParams(new FormData(filterForm));
            currentParams.append('get_all_ids', 'true');

            fetch(`get_items_handler.php?${currentParams.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.all_ids) {
                    isSelectAllFilteredActive = true;
                    allFilteredSelectedIds = data.all_ids;
                    document.querySelectorAll('#itemListContainer .item-checkbox').forEach(cb => {
                        cb.checked = true;
                        cb.disabled = true;
                    });
                    updateActionButtonsState();
                } else { throw new Error("Resposta inválida do servidor."); }
            })
            .catch(error => {
                console.error("Erro ao buscar todos os IDs:", error);
                alert("Ocorreu um erro ao tentar selecionar todos os itens. Tente novamente.");
                selectFilteredCheckbox.checked = false;
                isSelectAllFilteredActive = false;
            });

        } else {
            isSelectAllFilteredActive = false;
            allFilteredSelectedIds = [];
            document.querySelectorAll('#itemListContainer .item-checkbox').forEach(cb => {
                cb.checked = false;
                cb.disabled = false;
            });
            updateActionButtonsState();
        }
    }
    
    function fetchItemsAndUpdateList(page = 1) {
        isSelectAllFilteredActive = false;
        allFilteredSelectedIds = [];
        if (selectFilteredCheckbox) {
             selectFilteredCheckbox.checked = false;
             document.querySelectorAll('#itemListContainer .item-checkbox').forEach(cb => cb.disabled = false);
        }

        const params = new URLSearchParams(new FormData(filterForm));
        params.set('page', page);
        
        history.pushState(null, '', `?${params.toString()}`);

        if (itemCountContainer) itemCountContainer.innerHTML = 'Carregando...';

        fetch(`get_items_handler.php?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            totalFilteredItemsCount = data.total_items;
            renderItems(data.items, itemListContainer, current_user_is_admin);
            renderPagination(data.total_pages, data.current_page, params);
            renderItemCount(data);
            updateSelectFilteredCheckboxState();
        })
        .catch(error => {
            console.error('Erro ao buscar itens via AJAX:', error);
            if (itemListContainer) itemListContainer.innerHTML = '<p class="error-message">Erro ao carregar itens.</p>';
        });
    }

    // --- Funções de Renderização ---
    function renderItemCount(data) {
        if (!itemCountContainer) return;
        itemCountContainer.innerHTML = (data && data.total_items > 0) ?
            `<span style="font-size: 0.9em; color: #555;">Exibindo <strong>${data.items.length}</strong> de <strong>${data.total_items}</strong> itens</span>` : '';
    }

    function renderItems(items, container, isAdmin) {
        if (!container) return;
        container.innerHTML = '';

        if (!items || items.length === 0) {
            container.innerHTML = '<p class="info-message">Nenhum item encontrado com os filtros atuais.</p>';
            updateActionButtonsState();
            return;
        }

        const table = document.createElement('table');
        table.className = 'admin-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th class="checkbox-cell"></th><th class="id-cell">ID</th><th>Status</th><th class="wrap-text">Nome</th>
                    <th>Imagem C.B.</th><th>Categoria</th><th class="wrap-text">Local Encontrado</th><th class="wrap-text">Data Achado</th>
                    <th class="wrap-text">Dias Aguardando</th><th class="wrap-text">Registrado por</th><th>Ações</th>
                </tr>
            </thead>
            <tbody></tbody>`;

        const tbody = table.querySelector('tbody');
        items.forEach(item => {
            const tr = document.createElement('tr');
            const isChecked = isSelectAllFilteredActive;
            const isDisabled = isSelectAllFilteredActive;
            const formatDate = dateStr => new Date(dateStr + 'T00:00:00').toLocaleDateString('pt-BR');
            const statusClass = (item.status || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '-');
            const registeredByName = (item.registered_by_full_name || item.registered_by_username || 'Usuário Removido');
            let actionsContentHtml = `<div class="actions-wrapper">
                <i class="fas fa-search-plus action-icon action-view-description" data-tooltip="Ver Detalhes" data-description="${escapeHTML(item.description || '')}" data-image-path="${escapeHTML(item.image_path || '')}" data-itemid="${escapeHTML(item.id)}"></i>`;
            if (item.status === 'Pendente' && isAdmin) {
                actionsContentHtml += `<a href="admin/edit_item_page.php?id=${item.id}" class="action-icon" data-tooltip="Editar"><i class="fas fa-pen-to-square"></i></a>
                                       <i class="fas fa-trash action-icon action-icon-delete action-delete-item" data-tooltip="Excluir" data-item-id="${item.id}"></i>`;
            } else if (item.status === 'Devolvido' && item.devolution_document_id) {
                actionsContentHtml += `<a href="manage_devolutions.php?view_id=${item.devolution_document_id}" class="action-icon" data-tooltip="Ver Termo de Devolução"><i class="fas fa-file-lines"></i></a>`;
            } else if ((item.status === 'Doado' || item.status === 'Aprovado' || item.status === 'Em Aprovação') && item.donation_document_id) {
                 actionsContentHtml += `<a href="view_donation_term_page.php?term_id=${item.donation_document_id}" class="action-icon" data-tooltip="Ver Termo de Doação"><i class="fas fa-file-lines"></i></a>`;
            }
            actionsContentHtml += `</div>`;
            tr.innerHTML = `
                <td class="checkbox-cell"><input type="checkbox" class="item-checkbox" value="${item.id}" ${isChecked ? 'checked' : ''} ${isDisabled ? 'disabled' : ''}></td>
                <td class="id-cell">${escapeHTML(item.id)}</td>
                <td class="status-cell"><span class="item-status status-${statusClass}">${escapeHTML(item.status)}</span></td>
                <td class="wrap-text">${escapeHTML(item.name)}</td>
                <td>${item.barcode ? `<svg id="barcode-ajax-${item.id}" class="barcode-image"></svg>` : 'N/A'}</td>
                <td>${escapeHTML(item.category_name)} (${escapeHTML(item.category_code)})</td>
                <td class="wrap-text">${escapeHTML(item.location_name)}</td>
                <td>${formatDate(item.found_date)}</td>
                <td>${escapeHTML(item.days_waiting ?? '0')} dias</td>
                <td class="truncate-text">${escapeHTML(registeredByName)}</td>
                <td class="actions-cell home-actions-cell">${actionsContentHtml}</td>`;
            tbody.appendChild(tr);
        });

        container.appendChild(table);
        container.querySelectorAll('.item-checkbox').forEach(cb => cb.addEventListener('change', handleItemCheckboxChange));
        bindActionViewDescription(container);
        bindDeleteItemAction(container);
        renderBarcodes(items, true);
        updateActionButtonsState();
    }

    function bindActionViewDescription(container) {
        container.querySelectorAll('.action-view-description').forEach(button => {
            button.addEventListener('click', function() {
                const modal = document.getElementById('itemDetailModal');
                const description = this.dataset.description || 'Sem detalhes de descrição.';
                const imagePath = this.dataset.imagePath;

                let modalContent = modal.querySelector('.modal-content');
                if (!modalContent) {
                    modalContent = document.createElement('div');
                    modalContent.className = 'modal-content';
                    modal.appendChild(modalContent);
                }

                let imageHtml = '';
                if (imagePath) {
                    imageHtml = `
                        <h4>Foto do Item</h4>
                        <img src="${escapeHTML(imagePath)}" alt="Foto do item" class="modal-item-image">
                    `;
                }

                modalContent.innerHTML = `
                    <span class="modal-close-button">&times;</span>
                    <h3>Detalhes do Item</h3>
                    <h4>Descrição</h4>
                    <p>${escapeHTML(description)}</p>
                    ${imageHtml}
                `;
                modal.querySelector('.modal-close-button').onclick = () => modal.style.display = "none";
                modal.style.display = 'block';
            });
        });
    }

    function bindDeleteItemAction(container) {
        container.querySelectorAll('.action-delete-item').forEach(icon => {
            icon.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                if (!itemId) return;
                if (confirm('Tem certeza que deseja excluir este item?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'admin/delete_item_handler.php';
                    form.style.display = 'none';
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'id';
                    hiddenInput.value = itemId;
                    form.appendChild(hiddenInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    }

    function renderPagination(total_pages, current_page, current_params) {
        document.querySelectorAll('.pagination').forEach(paginationDiv => {
            paginationDiv.innerHTML = '';
            if (total_pages <= 1) return;
            let pages_to_render = [];
            const range = 2;
            let potential_pages = [1, total_pages];
            for (let i = current_page - range; i <= current_page + range; i++) {
                if (i > 1 && i < total_pages) potential_pages.push(i);
            }
            potential_pages = [...new Set(potential_pages)].sort((a, b) => a - b);
            let prev_page = 0;
            potential_pages.forEach(p => {
                if (p > prev_page + 1) pages_to_render.push('...');
                pages_to_render.push(p);
                prev_page = p;
            });
            const buildLink = (page) => {
                const params = new URLSearchParams(current_params);
                params.set('page', page);
                return `?${params.toString()}`;
            };
            let html = (current_page > 1) ? `<a href="${buildLink(1)}" data-page="1" class="pagination-link">Primeira Página</a>` : `<span class="pagination-link disabled">Primeira Página</span>`;
            pages_to_render.forEach(p => {
                if (p === '...') {
                    html += '<span class="pagination-dots">...</span>';
                } else {
                    html += (p == current_page) ? `<span class="pagination-link current-page">${p}</span>` : `<a href="${buildLink(p)}" data-page="${p}" class="pagination-link">${p}</a>`;
                }
            });
            html += (current_page < total_pages) ? `<a href="${buildLink(total_pages)}" data-page="${total_pages}" class="pagination-link">Última Página</a>` : `<span class="pagination-link disabled">Última Página</span>`;
            paginationDiv.innerHTML = html;

            paginationDiv.querySelectorAll('.pagination-link[data-page]').forEach(link => {
                link.addEventListener('click', e => {
                    e.preventDefault();
                    fetchItemsAndUpdateList(link.dataset.page);
                });
            });
        });
    }

    function setupActionButton(button, urlTemplate, alertMsg, confirmMsg = null) {
        if (!button) return;
        button.addEventListener('click', function() {
            const selectedIds = isSelectAllFilteredActive ? allFilteredSelectedIds : Array.from(document.querySelectorAll('#itemListContainer .item-checkbox:checked')).map(cb => cb.value);
            if (selectedIds.length === 0) {
                alert(alertMsg);
                return;
            }

            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }

            window.location.href = urlTemplate.replace('{ids}', selectedIds.join(','));
        });
    }

    // --- Vinculação de Eventos e Renderização Inicial (APENAS PARA HOME.PHP) ---
    if (document.getElementById('itemListContainer')) {
        initializeTooltips();

        if (filterForm) {
            filterForm.addEventListener('submit', e => {
                e.preventDefault();
                fetchItemsAndUpdateList(1);
            });
        }
        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', e => {
                e.preventDefault();
                filterForm.reset();
                fetchItemsAndUpdateList(1);
            });
        }
        if (itemNameInput) {
            itemNameInput.addEventListener('input', debounce(() => fetchItemsAndUpdateList(1), 350));
        }
        if (selectFilteredCheckbox) {
            selectFilteredCheckbox.addEventListener('change', handleSelectFilteredChange);
        }

        const modal = document.getElementById('itemDetailModal');
        if (modal) {
            window.onclick = e => {
                if (e.target == modal) modal.style.display = "none";
            };
        }

        setupActionButton(devolverButton, 'devolve_item_page.php?ids={ids}', 'Selecione ao menos um item para devolver.');
        setupActionButton(doarButton, 'generate_donation_term_page.php?item_ids={ids}', 'Selecione ao menos um item para doar.');
        setupActionButton(descartarButton, 'discard_items_handler.php?ids={ids}', 'Selecione ao menos um item para descartar.', 'Tem certeza que deseja descartar os itens selecionados? Esta ação não pode ser desfeita.');
        setupActionButton(imprimirCodBarrasButton, 'print_barcodes_page.php?ids={ids}', 'Selecione ao menos um item para imprimir o código de barras.');

        // Renderiza a tabela com os dados que o PHP já carregou na página
        totalFilteredItemsCount = initialTotalItems;
        renderItems(initial_php_items, itemListContainer, current_user_is_admin);
        const initialParams = new URLSearchParams(window.location.search);
        renderPagination(initialTotalPages, initialCurrentPage, initialParams);
        renderItemCount({
            items: initial_php_items,
            total_items: initialTotalItems
        });
        updateSelectFilteredCheckboxState();
    }
});