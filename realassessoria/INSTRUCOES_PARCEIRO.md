# Instruções para Implementação do Sistema de Usuário Parceiro

## 1. Execute o script SQL no banco de dados

Execute o arquivo `update_database_parceiro.sql` no seu MySQL/phpMyAdmin para adicionar os campos necessários:

```sql
-- 1. Adicionar campo tipo_usuario na tabela usuarios
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS tipo_usuario ENUM('admin', 'parceiro') DEFAULT 'parceiro' AFTER is_admin;

-- 2. Atualizar usuários existentes com is_admin = 1 para tipo admin
UPDATE usuarios SET tipo_usuario = 'admin' WHERE is_admin = 1;

-- 3. Adicionar campo usuario_cadastro_id na tabela clientes
ALTER TABLE clientes 
ADD COLUMN IF NOT EXISTS usuario_cadastro_id INT NULL AFTER observacao,
ADD INDEX idx_usuario_cadastro (usuario_cadastro_id);
```

## 2. Permissões do Usuário Parceiro

### O usuário parceiro tem acesso a:

**Visualização e Gerenciamento:**
- ✅ Listar apenas seus próprios clientes
- ✅ Cadastrar novos clientes (que ficam vinculados a ele)
- ✅ Editar seus próprios clientes
- ✅ Acessar financeiro dos seus clientes
- ✅ Gerar relatórios dos seus clientes
- ✅ Filtrar seus clientes

**Documentos (apenas para seus clientes):**
- ✅ Contrato
- ✅ Contrato 2
- ✅ Contrato 3
- ✅ Procuração
- ✅ Hipossuficiência
- ✅ Declaração de Residência
- ✅ Termo Aditivo
- ✅ Termo de Responsabilidade

**Restrições:**
- ❌ NÃO pode excluir clientes
- ❌ NÃO pode ver clientes de outros parceiros
- ❌ NÃO pode editar clientes de outros parceiros
- ❌ NÃO pode acessar área administrativa

### O usuário administrador mantém:
- ✅ Acesso total a todos os clientes
- ✅ Pode excluir clientes
- ✅ Pode gerenciar todos os usuários
- ✅ Acesso a todas as funcionalidades

## 3. Como criar um usuário parceiro

No banco de dados, ao criar um novo usuário:

```sql
INSERT INTO usuarios (nome, email, senha, is_admin, tipo_usuario) 
VALUES ('Nome do Parceiro', 'parceiro@email.com', 'hash_senha', 0, 'parceiro');
```

Ou alterar um usuário existente:

```sql
UPDATE usuarios SET tipo_usuario = 'parceiro' WHERE id = X;
```

## 4. Comportamento do Sistema

- Clientes cadastrados por parceiros ficam vinculados a eles através do campo `usuario_cadastro_id`
- Clientes antigos (com `usuario_cadastro_id = NULL`) são visíveis para todos (transição suave)
- Parceiros só veem clientes onde `usuario_cadastro_id = seu_id` ou `usuario_cadastro_id IS NULL`
- Administradores veem todos os clientes independente do `usuario_cadastro_id`

## 5. Arquivos Modificados

- ✅ login.php - Adiciona tipo_usuario na sessão
- ✅ cadastrar_cliente.php - Salva usuario_cadastro_id ao criar cliente
- ✅ listar_clientes.php - Filtra clientes por parceiro, oculta botão excluir
- ✅ editar_cliente.php - Verifica permissão para editar
- ✅ relatorio_clientes.php - Filtra relatórios por parceiro
- ✅ financeiro.php - Verifica permissão de acesso
- ✅ verificar_permissao.php - Novo arquivo para validar acesso a documentos
- ✅ Todos os arquivos gerar_*.php - Adicionada verificação de permissão
- ✅ excluir_cliente.php - Só admin pode excluir

## 6. Testando

1. Crie um usuário parceiro no banco
2. Faça login com esse usuário
3. Cadastre um novo cliente (ficará vinculado ao parceiro)
4. Verifique que só aparecem os clientes desse parceiro
5. Tente acessar documentos apenas dos seus clientes
6. Verifique que o botão "Excluir" não aparece

## 7. Observações Importantes

- O sistema mantém compatibilidade com clientes antigos (usuario_cadastro_id NULL)
- Logs de exclusão só funcionam para administradores
- A transição é suave - não afeta clientes existentes
