# INSTRUÇÕES PARA ADICIONAR AUDITORIA

## 1. Execute o SQL no phpMyAdmin

Acesse: http://localhost/phpmyadmin
Selecione o banco de dados: `sistema_clientes`
Vá na aba "SQL" e execute o arquivo: `adicionar_auditoria.sql`

Ou execute diretamente estas queries:

```sql
-- Adicionar colunas de auditoria
ALTER TABLE clientes 
ADD COLUMN created_by INT NULL COMMENT 'ID do usuário que criou o registro',
ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação',
ADD COLUMN updated_by INT NULL COMMENT 'ID do usuário que fez a última alteração',
ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última alteração';

-- Adicionar chaves estrangeiras (opcional, mas recomendado)
ALTER TABLE clientes 
ADD CONSTRAINT fk_clientes_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id),
ADD CONSTRAINT fk_clientes_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id);
```

## 2. O que foi implementado:

### cadastrar_cliente.php
- Agora registra o ID do usuário que criou o cliente no campo `created_by`
- A data/hora de criação é registrada automaticamente no campo `created_at`

### editar_cliente.php
- Adicionada verificação de sessão (session_start)
- Registra o ID do usuário que editou no campo `updated_by`
- A data/hora da edição é registrada automaticamente no campo `updated_at`
- Mostra um box de informações de auditoria no topo do formulário com:
  * Nome do usuário que cadastrou e quando
  * Nome do usuário que fez a última alteração e quando

## 3. Como funciona:

**Ao cadastrar um novo cliente:**
- O sistema salva automaticamente quem cadastrou (`created_by`)
- E quando foi cadastrado (`created_at`)

**Ao editar um cliente:**
- O sistema atualiza automaticamente quem editou (`updated_by`)
- E quando foi editado (`updated_at`)

**Na tela de edição:**
- Aparece um box azul no topo mostrando:
  * "Cadastrado por: Nome do Usuário em 24/11/2025 14:30"
  * "Última alteração por: Nome do Usuário em 24/11/2025 15:45"

## 4. Teste:

1. Execute o SQL acima
2. Faça login no sistema
3. Cadastre um novo cliente
4. Acesse a edição desse cliente
5. Você verá as informações de quem cadastrou
6. Faça alguma alteração e salve
7. Recarregue a página - verá as informações de quem editou

## Observações:

- Os registros antigos (antes desta implementação) terão `created_by` e `updated_by` como NULL
- Apenas os novos registros ou os editados após esta implementação terão essas informações
- Se quiser preencher os registros antigos com algum usuário padrão, execute:

```sql
UPDATE clientes 
SET created_by = 1 
WHERE created_by IS NULL;
```

(Substitua `1` pelo ID do usuário administrador)
