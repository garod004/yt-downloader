/**
 * Gera Procuração Incapaz
 */
function gerarProcuracaoIncapaz() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_incapaz.php', clienteId);
    }
}

/**
 * Gera Procuração a Rogo
 */
function gerarProcuracaoARogo() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_a_rogo.php', clienteId);
    }
}

/**
 * Gera Procuração Justiça Incapaz
 */
function gerarProcuracaoJusticaIncapaz() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_justica_incapaz.php', clienteId);
    }
}

/**
 * Gera Procuração Justiça a Rogo
 */
function gerarProcuracaoJusticaARogo() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_justica_a_rogo.php', clienteId);
    }
}

const SECOES_DOCUMENTOS_MODAL = [
    {
        titulo: 'Contratos Administrativo',
        itens: [
            { titulo: 'CONTRATO HONORÁRIOS PADRÃO', arquivo: 'gerar_contrato2.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO DE HONORÁRIOS PADRÃO FILHO MENOR', arquivo: 'gerar_contrato_honorarios_filho_menor.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO DE HONORÁRIOS PADRÃO INCAPAZ', arquivo: 'gerar_contrato_honorarios_incapaz.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO DE HONORÁRIOS PADRÃO A ROGO', arquivo: 'gerar_contrato_honorarios_a_rogo.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO 30%', arquivo: 'gerar_contrato.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO 30% FILHO MENOR', arquivo: 'gerar_contrato_honorarios_trintaporcento_filho_menor.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO 40%', arquivo: 'gerar_contrato_quarentaporcento.php', classe: 'btn-contrato', icone: 'fas fa-file-contract' }
        ]
    },
    {
        titulo: 'Contratos Jurídico',
        itens: [
            { titulo: 'CONTRATO JUSTIÇA 30%', arquivo: 'Documentos/contrato_juridico/contrato_trintaporcento_justica.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO JUSTIÇA FILHO MENOR 30%', arquivo: 'Documentos/contrato_juridico/contrato_trintaporcento_justica_filhomenor.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO JUSTIÇA PADRÃO', arquivo: 'Documentos/contrato_juridico/contrato_justica_padrao.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO JUSTIÇA PADRÃO FILHO MENOR', arquivo: 'Documentos/contrato_juridico/contrato_padrao_justica_filhomenor.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO JUSTIÇA A ROGO', arquivo: 'Documentos/contrato_juridico/contrato_justica_a_rogo.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' },
            { titulo: 'CONTRATO JUSTIÇA INCAPAZ', arquivo: 'Documentos/contrato_juridico/contrato_justica_incapaz.php', classe: 'btn-contrato-vermelho', icone: 'fas fa-file-contract' }
        ]
    },
    {
        titulo: 'Procuração Administrativa',
        itens: [
            { titulo: 'PROCURAÇÃO', arquivo: 'gerar_procuracao.php', classe: 'btn-procuracao', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO A ROGO', arquivo: 'gerar_procuracao_a_rogo.php', classe: 'btn-procuracao', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO FILHO MENOR', arquivo: 'gerar_procuracao_filho_menor.php', classe: 'btn-procuracao', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO INCAPAZ', arquivo: 'gerar_procuracao_incapaz.php', classe: 'btn-procuracao', icone: 'fas fa-file-signature' }
        ]
    },
    {
        titulo: 'Procuração Jurídica',
        itens: [
            { titulo: 'PROCURAÇÃO JUSTIÇA PADRÃO', arquivo: 'gerar_procuracao_justica_padrao.php', classe: 'btn-procuracao-vermelho', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO JUSTIÇA FILHO MENOR', arquivo: 'gerar_procuracao_justica_filho_menor.php', classe: 'btn-procuracao-vermelho', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO JUSTIÇA A ROGO', arquivo: 'gerar_procuracao_justica_a_rogo.php', classe: 'btn-procuracao-vermelho', icone: 'fas fa-file-signature' },
            { titulo: 'PROCURAÇÃO JUSTIÇA INCAPAZ', arquivo: 'gerar_procuracao_justica_incapaz.php', classe: 'btn-procuracao-vermelho', icone: 'fas fa-file-signature' }
        ]
    },
    {
        titulo: 'Declarações',
        itens: [
            { titulo: 'HIPOSSUFICIÊNCIA ADMINISTRATIVA', arquivo: 'gerar_hipossuficiencia.php', classe: 'btn-declaracao-verde', icone: 'fas fa-file-alt' },
            { titulo: 'HIPOSSUFICIÊNCIA JURÍDICA', arquivo: 'gerar_hipossuficiencia2.php', classe: 'btn-declaracao-vermelho', icone: 'fas fa-file-alt' },
            { titulo: 'DECLARAÇÃO DE RESIDÊNCIA ADMINISTRATIVA', arquivo: 'gerar_declaracao_residencia.php', classe: 'btn-declaracao-verde', icone: 'fas fa-file-alt' },
            { titulo: 'DECLARAÇÃO DE RESIDÊNCIA JURÍDICA', arquivo: 'gerar_declaracao_residencia.php', classe: 'btn-declaracao-vermelho', icone: 'fas fa-file-alt' }
        ]
    },
    {
        titulo: 'Relatórios',
        itens: [
            { titulo: 'RELATÓRIO DE AGENDAMENTOS', arquivo: 'gerar_relatorio_agendamentos.php', classe: 'btn-relatorio', icone: 'fas fa-calendar-check' }
        ]
    }
];
/* ===================================
   FUNÇÕES JAVASCRIPT PARA MODAL
   =================================== */

/**
 * Abre o modal de documentos
 */
function abrirModalDocumentos() {
    const modal = document.getElementById('modalDocumentos');
    if (modal) {
        modal.classList.add('ativo');
    }
}

/**
 * Fecha o modal de documentos
 */
function fecharModalDocumentos() {
    const modal = document.getElementById('modalDocumentos');
    if (modal) {
        modal.classList.remove('ativo');
    }
}

/**
 * Abre uma aba específica do modal
 * @param {Event} event - Evento do click
 * @param {String} abaId - ID da aba a ser aberta
 */
function abrirAba(event, abaId) {
    event.preventDefault();

    // Remove a classe active de todos os botões e conteúdos
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(btn => btn.classList.remove('active'));
    tabContents.forEach(content => content.classList.remove('active'));

    // Adiciona a classe active ao botão clicado e seu conteúdo
    event.target.classList.add('active');
    const tabContent = document.getElementById(abaId);
    if (tabContent) {
        tabContent.classList.add('active');
    }
}

/**
 * Fecha o modal ao clicar fora dele
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalDocumentos');

    criarSeletorAdvogadoDocumentos();
    carregarAdvogadosParaDocumentos();
    renderizarSecoesDocumentosModal();
    
    if (modal) {
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                fecharModalDocumentos();
            }
        });
    }
});

function renderizarSecoesDocumentosModal() {
    const grid = document.querySelector('#modalDocumentos .documentos-grid');
    if (!grid) {
        return;
    }

    grid.innerHTML = '';

    SECOES_DOCUMENTOS_MODAL.forEach(function(secaoConfig) {
        const secao = document.createElement('section');
        secao.className = 'documentos-secao';

        const titulo = document.createElement('h3');
        titulo.className = 'documentos-secao-titulo';
        titulo.textContent = secaoConfig.titulo;
        secao.appendChild(titulo);

        const grade = document.createElement('div');
        grade.className = 'documentos-secao-grid';

        secaoConfig.itens.forEach(function(item) {
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'btn-documento ' + item.classe;
            botao.innerHTML = '<i class="' + item.icone + '"></i><span class="btn-titulo">' + item.titulo + '</span>';
            botao.addEventListener('click', function() {
                abrirArquivoDaPastaDocumentos(item.arquivo);
            });
            grade.appendChild(botao);
        });

        secao.appendChild(grade);
        grid.appendChild(secao);
    });
}

function garantirBotaoContratoHonorariosPadrao() {
    const grid = document.querySelector('#modalDocumentos .documentos-grid');
    if (!grid) {
        return;
    }

    const botaoExistente = grid.querySelector('button[onclick="gerarContrato()"], button[onclick="gerarContratoHonorariosPadrao()"]');
    if (botaoExistente) {
        if (botaoExistente.getAttribute('onclick') !== 'gerarContrato()') {
            botaoExistente.setAttribute('onclick', 'gerarContrato()');
        }
        return;
    }

    const botao = document.createElement('button');
    botao.className = 'btn-documento btn-contrato';
    botao.setAttribute('onclick', 'gerarContrato()');
    botao.innerHTML = '<i class="fas fa-file-contract"></i><span class="btn-titulo">CONTRATO HONORARIOS PADRAO</span>';

    if (grid.firstChild) {
        grid.insertBefore(botao, grid.firstChild);
    } else {
        grid.appendChild(botao);
    }
}

const ARQUIVOS_PASTA_DOCUMENTOS = [
    'gerar_contrato4.php',
    'Documentos/contrato_juridico/contrato_trintaporcento_justica.php',
    'Documentos/contrato_juridico/contrato_padrao_justica_filhomenor.php',
    'Documentos/contrato_juridico/contrato_trintaporcento_justica_filhomenor.php',
    'Documentos/contrato_juridico/contrato_justica_a_rogo.php',
    'Documentos/contrato_juridico/contrato_justica_incapaz.php',
    'gerar_declaracao_residencia.php',
    'gerar_hipossuficiencia.php',
    'gerar_hipossuficiencia2.php',
    'gerar_procuracao.php',
    'gerar_procuracao_filho_menor.php',
    'gerar_procuracao_justica_filho_menor.php',
    'gerar_procuracao_justica_padrao.php',
    'gerar_procuracao_incapaz.php',
    'gerar_procuracao_a_rogo.php',
    'gerar_procuracao_justica_incapaz.php',
    'gerar_procuracao_justica_a_rogo.php'
];

function criarSeletorAdvogadoDocumentos() {
    const container = document.querySelector('#modalDocumentos .modal-container');
    const grid = document.querySelector('#modalDocumentos .documentos-grid');
    if (!container || !grid || document.getElementById('advogadoSelect')) {
        return;
    }

    const faixa = document.createElement('div');
    faixa.className = 'advogado-seletor-faixa';

    const label = document.createElement('label');
    label.className = 'advogado-seletor-label';
    label.setAttribute('for', 'advogadoSelect');
    label.textContent = 'Advogado contratado';

    const select = document.createElement('select');
    select.id = 'advogadoSelect';
    select.className = 'advogado-seletor-select';
    select.innerHTML = '<option value="">Carregando advogados...</option>';

    const acao = document.createElement('a');
    acao.href = 'cadastro_advogados.php';
    acao.target = '_blank';
    acao.rel = 'noopener noreferrer';
    acao.className = 'advogado-seletor-link';
    acao.textContent = 'Cadastrar advogados';

    select.addEventListener('change', function() {
        try {
            localStorage.setItem('advogadoSelecionadoId', select.value || '');
        } catch (e) {
            // Ignora erro de armazenamento local.
        }
    });

    faixa.appendChild(label);
    faixa.appendChild(select);
    faixa.appendChild(acao);
    container.insertBefore(faixa, grid);
}

function carregarAdvogadosParaDocumentos() {
    const select = document.getElementById('advogadoSelect');
    if (!select) {
        return;
    }

    fetch('listar_advogados.php', { cache: 'no-store' })
        .then(function(response) {
            return response.json();
        })
        .then(function(payload) {
            const itens = payload && Array.isArray(payload.items) ? payload.items : [];
            select.innerHTML = '';

            if (itens.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Nenhum advogado ativo cadastrado';
                select.appendChild(opt);
                return;
            }

            let escolhido = '';
            try {
                escolhido = localStorage.getItem('advogadoSelecionadoId') || '';
            } catch (e) {
                escolhido = '';
            }

            itens.forEach(function(item) {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                const documento = item.documento ? ' - ' + item.documento : '';
                opt.textContent = item.nome + documento;
                select.appendChild(opt);
            });

            const existeEscolhido = itens.some(function(item) {
                return String(item.id) === String(escolhido);
            });

            select.value = existeEscolhido ? String(escolhido) : String(itens[0].id);

            try {
                localStorage.setItem('advogadoSelecionadoId', select.value || '');
            } catch (e) {
                // Ignora erro de armazenamento local.
            }
        })
        .catch(function() {
            select.innerHTML = '<option value="">Erro ao carregar advogados</option>';
        });
}

function obterAdvogadoIdSelecionado() {
    const select = document.getElementById('advogadoSelect');
    if (!select || !select.value) {
        alert('⚠️ Selecione um advogado contratado antes de gerar o documento.');
        return null;
    }
    return select.value;
}

function resolverCaminhoDocumento(caminhoArquivo) {
    if (!caminhoArquivo || caminhoArquivo.indexOf('/') !== -1) {
        return caminhoArquivo;
    }

    if (caminhoArquivo.indexOf('gerar_procuracao_justica_') === 0) {
        return 'Documentos/procuracao_jus/' + caminhoArquivo;
    }

    if (caminhoArquivo.indexOf('gerar_contrato') === 0) {
        return 'Documentos/contrato/' + caminhoArquivo;
    }

    if (caminhoArquivo === 'gerar_declaracao_residencia.php') {
        return 'Documentos/declaracao_residencia/' + caminhoArquivo;
    }

    if (caminhoArquivo.indexOf('gerar_hipossuficiencia') === 0) {
        return 'Documentos/hipossuficiencia/' + caminhoArquivo;
    }

    if (caminhoArquivo.indexOf('gerar_procuracao') === 0) {
        return 'Documentos/procuracao_adm/' + caminhoArquivo;
    }

    return caminhoArquivo;
}

function abrirDocumentoComParametros(caminhoArquivo, clienteId) {
    const advogadoId = obterAdvogadoIdSelecionado();
    if (!advogadoId) {
        return;
    }

    const caminhoResolvido = resolverCaminhoDocumento(caminhoArquivo);
    const url = caminhoResolvido + '?id=' + encodeURIComponent(clienteId) + '&advogado_id=' + encodeURIComponent(advogadoId);
    fecharModalDocumentos();
    baixarPdfSemSairDoSistema(url, 'documento.pdf');
}

function extrairNomeArquivoDownload(contentDisposition, fallbackName) {
    if (!contentDisposition) {
        return fallbackName;
    }

    const utf8Match = contentDisposition.match(/filename\*=UTF-8''([^;]+)/i);
    if (utf8Match && utf8Match[1]) {
        try {
            return decodeURIComponent(utf8Match[1]).replace(/[\\/:*?"<>|]+/g, '_');
        } catch (e) {
            // Ignora e tenta o nome simples abaixo.
        }
    }

    const simpleMatch = contentDisposition.match(/filename="?([^";]+)"?/i);
    if (simpleMatch && simpleMatch[1]) {
        return simpleMatch[1].replace(/[\\/:*?"<>|]+/g, '_');
    }

    return fallbackName;
}

function baixarPdfSemSairDoSistema(url, fallbackName) {
    fetch(url, {
        method: 'GET',
        credentials: 'same-origin'
    })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Falha ao gerar o documento.');
            }

            const contentDisposition = response.headers.get('content-disposition') || '';
            return response.blob().then(function(blob) {
                return {
                    blob: blob,
                    nomeArquivo: extrairNomeArquivoDownload(contentDisposition, fallbackName)
                };
            });
        })
        .then(function(resultado) {
            const blobUrl = URL.createObjectURL(resultado.blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = resultado.nomeArquivo;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(function() {
                URL.revokeObjectURL(blobUrl);
            }, 2000);
        })
        .catch(function() {
            const iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = url;
            document.body.appendChild(iframe);
            setTimeout(function() {
                iframe.remove();
            }, 15000);
        });
}

function verificarFilhoMenorCadastrado(clienteId) {
    const body = new URLSearchParams();
    body.set('acao', 'listar');
    body.set('cliente_id', String(clienteId));

    return fetch('salvar_filho_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString(),
        cache: 'no-store'
    })
        .then(function(response) {
            return response.json();
        })
        .then(function(payload) {
            return !!(payload && payload.success && Array.isArray(payload.filhos) && payload.filhos.length > 0);
        });
}

function verificarARogoCadastrado(clienteId) {
    const body = new URLSearchParams();
    body.set('acao', 'listar');
    body.set('cliente_id', String(clienteId));

    return fetch('salvar_a_rogo_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString(),
        cache: 'no-store'
    })
        .then(function(response) {
            return response.json();
        })
        .then(function(payload) {
            return !!(payload && payload.success && Array.isArray(payload.registros) && payload.registros.length > 0);
        });
}

function verificarIncapazCadastrado(clienteId) {
    const body = new URLSearchParams();
    body.set('acao', 'listar');
    body.set('cliente_id', String(clienteId));

    return fetch('salvar_incapaz_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString(),
        cache: 'no-store'
    })
        .then(function(response) {
            return response.json();
        })
        .then(function(payload) {
            return !!(payload && payload.success && Array.isArray(payload.registros) && payload.registros.length > 0);
        });
}

function abrirDocumentoComFilhoMenorObrigatorio(caminhoArquivo) {
    const clienteId = obterClienteId();
    if (!clienteId) {
        return;
    }

    verificarFilhoMenorCadastrado(clienteId)
        .then(function(possuiFilhoMenor) {
            if (!possuiFilhoMenor) {
                alert('⚠️ Cadastre um filho menor na ficha do cliente antes de gerar este documento.');
                return;
            }

            abrirDocumentoComParametros(caminhoArquivo, clienteId);
        })
        .catch(function() {
            alert('⚠️ Não foi possível verificar os dados do filho menor agora. Tente novamente.');
        });
}

function abrirDocumentoComARogoObrigatorio(caminhoArquivo) {
    const clienteId = obterClienteId();
    if (!clienteId) {
        return;
    }

    verificarARogoCadastrado(clienteId)
        .then(function(possuiARogo) {
            if (!possuiARogo) {
                alert('⚠️ Cadastre os dados de A ROGO na ficha do cliente antes de gerar este documento.');
                return;
            }

            abrirDocumentoComParametros(caminhoArquivo, clienteId);
        })
        .catch(function() {
            alert('⚠️ Não foi possível verificar os dados de A ROGO agora. Tente novamente.');
        });
}

function abrirDocumentoComIncapazObrigatorio(caminhoArquivo) {
    const clienteId = obterClienteId();
    if (!clienteId) {
        return;
    }

    verificarIncapazCadastrado(clienteId)
        .then(function(possuiIncapaz) {
            if (!possuiIncapaz) {
                alert('⚠️ Cadastre os dados do incapaz na ficha do cliente antes de gerar este documento.');
                return;
            }

            abrirDocumentoComParametros(caminhoArquivo, clienteId);
        })
        .catch(function() {
            alert('⚠️ Não foi possível verificar os dados do incapaz agora. Tente novamente.');
        });
}

function ativarTodosArquivosDaPastaDocumentos() {
    const grid = document.querySelector('#modalDocumentos .documentos-grid');
    if (!grid) {
        return;
    }

    if (grid.querySelector('[data-documentos-catalogo="true"]')) {
        return;
    }

    const arquivosPorCategoria = {
        contratos: [],
        procuracoes: [],
        relatorios: []
    };

    ARQUIVOS_PASTA_DOCUMENTOS.forEach(function(nomeArquivo) {
        const categoria = obterCategoriaArquivoDocumentos(nomeArquivo);
        arquivosPorCategoria[categoria].push(nomeArquivo);
    });

    Object.keys(arquivosPorCategoria).forEach(function(categoria) {
        arquivosPorCategoria[categoria].sort(function(a, b) {
            return obterTituloBotaoDocumento(a).localeCompare(obterTituloBotaoDocumento(b), 'pt-BR', {
                sensitivity: 'base',
                numeric: true
            });
        });
    });

    const catalogo = document.createElement('div');
    catalogo.className = 'documentos-catalogo';
    catalogo.setAttribute('data-documentos-catalogo', 'true');

    const cabecalho = document.createElement('h3');
    cabecalho.className = 'documentos-catalogo-titulo';
    cabecalho.textContent = 'Arquivos da pasta Documentos';
    catalogo.appendChild(cabecalho);

    catalogo.appendChild(criarSecaoCategoriaDocumentos('Justiça', arquivosPorCategoria.contratos));
    catalogo.appendChild(criarSecaoCategoriaDocumentos('Procurações', arquivosPorCategoria.procuracoes));
    catalogo.appendChild(criarSecaoCategoriaDocumentos('Relatórios e Outros', arquivosPorCategoria.relatorios));

    grid.appendChild(catalogo);
}

function criarSecaoCategoriaDocumentos(tituloCategoria, arquivos) {
    const secao = document.createElement('section');
    secao.className = 'documentos-categoria';

    const titulo = document.createElement('h4');
    titulo.className = 'documentos-categoria-titulo';
    titulo.textContent = tituloCategoria;
    secao.appendChild(titulo);

    const gridCategoria = document.createElement('div');
    gridCategoria.className = 'documentos-categoria-grid';

    arquivos.forEach(function(nomeArquivo) {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'btn-documento ' + obterClasseBotaoDocumento(nomeArquivo);
        botao.setAttribute('data-arquivo-documentos', nomeArquivo);
        botao.innerHTML = '<i class="' + obterIconeBotaoDocumento(nomeArquivo) + '"></i><span class="btn-titulo">' + obterTituloBotaoDocumento(nomeArquivo) + '</span>';
        botao.addEventListener('click', function() {
            abrirArquivoDaPastaDocumentos(nomeArquivo);
        });
        gridCategoria.appendChild(botao);
    });

    secao.appendChild(gridCategoria);
    return secao;
}

function abrirArquivoDaPastaDocumentos(nomeArquivo) {
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_justica_a_rogo.php') {
        abrirDocumentoComARogoObrigatorio(nomeArquivo);
        return;
    }

    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_justica_incapaz.php') {
        abrirDocumentoComIncapazObrigatorio(nomeArquivo);
        return;
    }

    if (nomeArquivo === 'gerar_procuracao_justica_incapaz.php') {
        const clienteIdIncapaz = obterClienteId();
        if (!clienteIdIncapaz) {
            return;
        }
        abrirDocumentoComParametros('Documentos/procuracao_jus/gerar_procuracao_justica_incapaz.php', clienteIdIncapaz);
        return;
    }

    const clienteId = obterClienteId();
    if (!clienteId) {
        return;
    }

    abrirDocumentoComParametros(nomeArquivo, clienteId);
}

function obterClasseBotaoDocumento(nomeArquivo) {
    if (nomeArquivo.indexOf('contrato7') !== -1) {
        return 'btn-contrato btn-contrato7-vermelho';
    }
    if (nomeArquivo.indexOf('contrato6') !== -1) {
        return 'btn-contrato btn-contrato6-vermelho';
    }
    if (nomeArquivo === 'gerar_contrato4.php') {
        return 'btn-contrato btn-contrato-vermelho';
    }
    if (nomeArquivo === 'gerar_contrato2.php') {
        return 'btn-contrato btn-contrato2-vermelho';
    }
    if (nomeArquivo === 'gerar_contrato.php') {
        return 'btn-contrato btn-contrato-vermelho';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_trintaporcento_justica.php') {
        return 'btn-contrato btn-contrato5-vermelho';
    }
    if (nomeArquivo === 'gerar_procuracao2.php' || nomeArquivo === 'gerar_procuracao_justica_filho_menor.php') {
        return 'btn-procuracao btn-procuracao2-vermelho';
    }
    if (nomeArquivo === 'gerar_procuracao_filho_menor.php') {
        return 'btn-procuracao';
    }
    if (nomeArquivo === 'gerar_procuracao3.php' || nomeArquivo === 'gerar_procuracao_justica_padrao.php') {
        return 'btn-procuracao btn-procuracao3-vermelho';
    }
    if (nomeArquivo === 'gerar_procuracao_justica_a_rogo.php') {
        return 'btn-procuracao btn-procuracao3-vermelho';
    }
    if (nomeArquivo === 'gerar_hipossuficiencia2.php') {
        return 'btn-hipo btn-hipo2-vermelho';
    }
    if (nomeArquivo.indexOf('contrato') !== -1) {
        return 'btn-contrato';
    }
    if (nomeArquivo.indexOf('procuracao') !== -1) {
        return 'btn-procuracao';
    }
    return 'btn-relatorio';
}

function obterCategoriaArquivoDocumentos(nomeArquivo) {
    if (nomeArquivo.indexOf('contrato') !== -1) {
        return 'contratos';
    }
    if (nomeArquivo.indexOf('procuracao') !== -1) {
        return 'procuracoes';
    }
    return 'relatorios';
}

function obterIconeBotaoDocumento(nomeArquivo) {
    if (nomeArquivo.indexOf('contrato') !== -1) {
        return 'fas fa-file-contract';
    }
    if (nomeArquivo.indexOf('procuracao') !== -1) {
        return 'fas fa-file-signature';
    }
    if (nomeArquivo.indexOf('declaracao') !== -1 || nomeArquivo.indexOf('hipossuficiencia') !== -1) {
        return 'fas fa-file-alt';
    }
    if (nomeArquivo.indexOf('prontuario') !== -1) {
        return 'fas fa-notes-medical';
    }
    return 'fas fa-file-pdf';
}

function obterTituloBotaoDocumento(nomeArquivo) {
    if (nomeArquivo === 'gerar_contrato4.php') {
        return 'CONTRATO JUSTIÇA PADRÃO';
    }
    if (nomeArquivo === 'gerar_contrato.php') {
        return 'CONTRATO JUSTIÇA 30%';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_trintaporcento_justica.php') {
        return 'CONTRATO JUSTIÇA 30%';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_trintaporcento_justica_filhomenor.php') {
        return 'CONTRATO JUSTIÇA FILHO MENOR 30%';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_padrao_justica_filhomenor.php') {
        return 'CONTRATO JUSTIÇA PADRÃO FILHO MENOR';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_justica_a_rogo.php') {
        return 'CONTRATO JUSTIÇA A ROGO';
    }
    if (nomeArquivo === 'Documentos/contrato_juridico/contrato_justica_incapaz.php') {
        return 'CONTRATO JUSTIÇA INCAPAZ';
    }
    if (nomeArquivo === 'gerar_procuracao2.php' || nomeArquivo === 'gerar_procuracao_justica_filho_menor.php') {
        return 'PROCURAÇÃO JUSTIÇA FILHO MENOR';
    }
    if (nomeArquivo === 'gerar_procuracao_filho_menor.php') {
        return 'PROCURAÇÃO FILHO MENOR';
    }
    if (nomeArquivo === 'gerar_procuracao3.php' || nomeArquivo === 'gerar_procuracao_justica_padrao.php') {
        return 'PROCURAÇÃO JUSTIÇA PADRÃO';
    }
    if (nomeArquivo === 'gerar_procuracao_incapaz.php') {
        return 'PROCURAÇÃO INCAPAZ';
    }
    if (nomeArquivo === 'gerar_procuracao_a_rogo.php') {
        return 'PROCURAÇÃO A ROGO';
    }
    if (nomeArquivo === 'gerar_procuracao_justica_incapaz.php') {
        return 'PROCURAÇÃO JUSTIÇA INCAPAZ';
    }
    if (nomeArquivo === 'gerar_procuracao_justica_a_rogo.php') {
        return 'PROCURAÇÃO JUSTIÇA A ROGO';
    }
    if (nomeArquivo === 'gerar_hipossuficiencia2.php') {
        return 'HIPOSSUFICIÊNCIA JUSTIÇA';
    }
    let titulo = nomeArquivo.replace('.php', '');
    titulo = titulo.replace(/^gerar_/, '');
    titulo = titulo.replace(/^gerador_/, '');
    titulo = titulo.replace(/_/g, ' ');
    return titulo.toUpperCase();
}

/* ===================================
   FUNÇÕES PARA GERAR DOCUMENTOS
   Personalize conforme seu projeto
   =================================== */

/**
 * Obtém o ID do cliente selecionado
 */
function obterClienteId() {
    const clienteId = document.getElementById('cliente_id_hidden').value;
    if (!clienteId) {
        alert('⚠️ Selecione um cliente primeiro!');
        return null;
    }
    return clienteId;
}

/**
 * Gera Contrato de Honorários Padrão
 */
function gerarContratoHonorariosPadrao() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato.php', clienteId);
    }
}

/**
 * Gera Contrato de Honorários Incapaz
 */
function gerarContratoHonorariosIncapaz() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato_honorarios_incapaz.php', clienteId);
    }
}

/**
 * Gera Contrato de Honorarios Filho Menor
 */
function gerarContratoHonorariosFilhoMenor() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato_honorarios_filho_menor.php', clienteId);
    }
}

/**
 * Gera Contrato de Honorários por A ROGO
 */
function gerarContratoHonorariosARogo() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato_honorarios_a_rogo.php', clienteId);
    }
}

/**
 * Gera Contrato
 */
function gerarContrato() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato.php', clienteId);
    }
}

/**
 * Gera Contrato 2
 */
function gerarContrato2() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato2.php', clienteId);
    }
}

/**
 * Gera Contrato 3
 */
function gerarContrato3() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato_quarentaporcento.php', clienteId);
    }
}

/**
 * Gera Contrato 4
 */
function gerarContrato4() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_contrato4.php', clienteId);
    }
}

/**
 * Gera Contrato 30% Filho Menor
 */
function gerarContratoHonorariosTrintaPorcentoFilhoMenor() {
    abrirDocumentoComFilhoMenorObrigatorio('gerar_contrato_honorarios_trintaporcento_filho_menor.php');
}

/**
 * Gera Contrato 5
 */
function gerarContrato5() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('Documentos/contrato_juridico/contrato_trintaporcento_justica.php', clienteId);
    }
}

/**
 * Gera Contrato 6
 */
function gerarContrato6() {
    abrirDocumentoComFilhoMenorObrigatorio('Documentos/contrato_juridico/contrato_padrao_justica_filhomenor.php');
}

/**
 * Gera Contrato 7
 */
function gerarContrato7() {
    abrirDocumentoComFilhoMenorObrigatorio('Documentos/contrato_juridico/contrato_trintaporcento_justica_filhomenor.php');
}

/**
 * Gera Procuração
 */
function gerarProcuracao() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao.php', clienteId);
    }
}

/**
 * Gera Procuração Filho Menor
 */
function gerarProcuracaoFilhoMenor() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_filho_menor.php', clienteId);
    }
}

/**
 * Gera Procuração A Rogo
 */
function gerarProcuracaoARogo() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_a_rogo.php', clienteId);
    }
}

/**
 * Gera Procuração 2
 */
function gerarProcuracao2() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_justica_filho_menor.php', clienteId);
    }
}

/**
 * Gera Procuração 3
 */
function gerarProcuracao3() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_procuracao_justica_padrao.php', clienteId);
    }
}

/**
 * Gera Termo Aditivo
 */
function gerarTermoAditivo() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_termo_aditivo.php', clienteId);
    }
}

/**
 * Gera Termo de Responsabilidade
 */
function gerarTermoResponsabilidade() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_termo_responsabilidade.php', clienteId);
    }
}

/**
 * Agendar Atendimento
 */
function agendarAtendimento() {
    const clienteId = obterClienteId();
    if (clienteId) {
        abrirDocumentoComParametros('gerar_relatorio_agendamentos.php', clienteId);
    }
}
