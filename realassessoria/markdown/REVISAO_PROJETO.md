# Revisão Técnica do Projeto

## Escopo

Revisão técnica ampla do projeto com foco em segurança, corretude, regressões, persistência, autenticação, upload de arquivos e funcionamento dos formulários principais.

Observação: esta revisão foi feita por inspeção estática do código disponível no workspace. Não houve execução completa do sistema nem cobertura automatizada de fluxos fim a fim.

## Achados

### Crítico

#### 1. Credenciais sensíveis hardcoded no código e no arquivo de ambiente

- Onde: conexao.php, linhas 75, 76 e 83; .env, linhas 10, 28 e 29
- Problema: o projeto mantém usuário e senha de banco, senha local e credenciais SMTP em texto puro no código e no `.env` dentro do workspace.
- Impacto: qualquer acesso ao código ou a backup do projeto expõe banco e envio de e-mail, com possibilidade de vazamento, alteração de dados e abuso de infraestrutura.
- Correção sugerida: remover defaults sensíveis do código, tirar `.env` do versionamento, rotacionar todas as credenciais já expostas e usar apenas variáveis de ambiente seguras no servidor.

#### 2. Bypass de autenticação administrativa por credencial fixa

- Onde: login.php, linha 65
- Problema: existe um atalho de login com e-mail e senha fixos no código, fora do fluxo normal do banco.
- Impacto: qualquer pessoa que conheça ou obtenha essas credenciais entra como administrador sem depender do cadastro real do sistema.
- Correção sugerida: remover o bloco de acesso fixo e manter autenticação exclusivamente por credencial armazenada com hash no banco.

#### 3. Senhas sensíveis de clientes armazenadas em texto puro

- Onde: editar_cliente.php, linhas 128, 129, 175 e 176
- Problema: os campos `senha_email` e `senha_meuinss` são recebidos do formulário e persistidos diretamente no banco.
- Impacto: vazamento de banco, backup ou log expõe credenciais pessoais de clientes, inclusive de serviços externos e governamentais.
- Correção sugerida: evitar armazenar essas senhas. Se o negócio realmente exigir armazenamento, usar criptografia forte em repouso, controle de acesso rígido e política de mascaramento.

#### 4. Backups SQL sensíveis dentro do webroot do projeto

- Onde: pasta backups, com múltiplos arquivos `.sql` e `.zip`
- Problema: o projeto mantém backups completos dentro de uma pasta pública da aplicação.
- Impacto: além do risco de versionamento indevido, qualquer falha de servidor, deploy ou configuração pode expor dados massivos de clientes.
- Correção sugerida: mover backups para fora do webroot, excluir arquivos sensíveis do repositório e proteger o processo com armazenamento restrito e política de retenção.

### Alto

#### 5. Formulário de cadastro aponta para endpoint inexistente

- Onde: cadastro.html, linha 11
- Problema: o formulário usa `action="cadastrar.php"`, mas esse arquivo não existe no projeto.
- Impacto: o fluxo de cadastro falha ao enviar, mesmo que a interface esteja correta.
- Correção sugerida: apontar o formulário para o endpoint real existente ou criar o endpoint esperado.

#### 6. Atualização de cliente com `bind_param` obviamente inconsistente

- Onde: editar_cliente.php, linha 193
- Problema: o código usa `$tipos_correto = 'sssssssssssssssss...';`, que é um placeholder inválido para `bind_param` e não representa uma definição real de tipos.
- Impacto: o update pode falhar em runtime, causar erro intermitente e impedir a gravação de alterações do cliente.
- Correção sugerida: substituir a string por uma definição real e consistente com a quantidade e o tipo de parâmetros usados no `UPDATE`.

#### 7. XSS potencial por uso de `innerHTML` com valor dinâmico

- Onde: abrir_meuinss.php, linhas 82 e 101
- Problema: o CPF é concatenado em `innerHTML` para montar HTML dinâmico.
- Impacto: se esse valor puder ser influenciado fora do caminho esperado, pode haver injeção de HTML ou script no navegador.
- Correção sugerida: usar `textContent` para conteúdo textual ou montar o DOM com `createElement` e atribuição segura de texto.

#### 8. Upload validado apenas por extensão e salvo em diretório acessível da aplicação

- Onde: processar_cnis.php, linhas 122, 123 e 131
- Problema: o upload aceita arquivo com base apenas na extensão e salva em `uploads_cnis/`, sem `.htaccess` protetivo encontrado no diretório.
- Impacto: aumenta o risco de upload malicioso, conteúdo disfarçado e exposição direta de arquivos sensíveis.
- Correção sugerida: validar MIME real, gerar nome interno, mover uploads para fora do webroot e bloquear acesso direto por configuração do servidor.

#### 9. Ausência aparente de proteção CSRF nos formulários próprios da aplicação

- Onde: revisão dos arquivos PHP e HTML do nível principal do projeto
- Problema: não foram encontrados tokens CSRF nem validação correspondente nos formulários principais.
- Impacto: um usuário autenticado pode ser induzido a disparar ações administrativas ou de alteração de dados a partir de outro site.
- Correção sugerida: implementar token CSRF por sessão e validação obrigatória em todos os POSTs e endpoints AJAX com efeito de escrita.

### Médio

#### 10. Erro de conexão expõe detalhe técnico diretamente na resposta

- Onde: conexao.php, linha 106
- Problema: em falha de conexão, o código usa `die(...)` exibindo detalhes técnicos da conexão principal e fallback.
- Impacto: mensagens de erro em produção facilitam reconhecimento de infraestrutura, host, usuário e comportamento interno.
- Correção sugerida: registrar detalhes apenas no log seguro e exibir ao usuário uma mensagem genérica sem detalhes internos.

#### 11. Logs de debug com dados operacionais e possivelmente pessoais

- Onde: editar_cliente.php, linhas 133, 253 e 254
- Problema: há `error_log` com conteúdo de observação e identificação de cliente durante operações sensíveis.
- Impacto: logs podem virar vetor de vazamento de informação pessoal ou de dados internos do negócio.
- Correção sugerida: remover logs temporários, anonimizar conteúdo operacional e nunca registrar informação sensível de cliente em texto puro.

## Riscos residuais

- Não há suíte de testes automatizados visível para fluxos críticos de login, cadastro, atualização de cliente e upload.
- O projeto mantém muitos scripts auxiliares e corretivos no diretório principal, o que aumenta o risco operacional e a dificuldade de manutenção.
- Há forte dependência de PHP procedural espalhado, o que torna validação centralizada de segurança e autorização mais difícil.

## Prioridade recomendada

### Imediato

- Remover credenciais hardcoded do código e rotacionar segredos já expostos.
- Eliminar o bypass administrativo fixo do login.
- Corrigir o endpoint quebrado do formulário de cadastro.
- Revisar urgentemente o armazenamento de senhas de clientes.

### Curto prazo

- Corrigir o `bind_param` do fluxo de edição de cliente.
- Implementar proteção CSRF.
- Endurecer o fluxo de upload CNIS.
- Remover exposição detalhada de erro e limpar logs sensíveis.
