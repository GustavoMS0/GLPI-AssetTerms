# Asset Terms

**Termos de responsabilidade para o GLPI.** Gere o termo de **entrega** ou de **devolução** de um equipamento direto na ficha do computador. O colaborador assina na tela, na hora ou **pelo link enviado por e-mail**, e o PDF fica arquivado no GLPI.

```
Ativos › Computadores › NB-COLAB01 › aba "Termo de Responsabilidade"

  ( • ) Entrega ao colaborador      (   ) Devolução à TI
  Colaborador: [ Maria Souza          ▾ ]   Status após o termo: [ Em uso ▾ ]
  Acessórios:  [x] Fonte  [x] Cabo de força  [ ] Mochila  [ ] Mouse ...
  ┌──────────────────────────────────────────────┐
  │            ~ assinatura na tela ~            │
  └──────────────────────────────────────────────┘
  [ ✓ Gerar e arquivar termo ]  [ ✉ Enviar por e-mail para assinatura ]  [ 🖨 PDF para o papel ]
```

| Versão do GLPI | Suporte |
|---|---|
| **11.0.x** | ✅ |
| **10.0.x** | ✅ |

---

## Funcionalidades

- **Aba "Termo de Responsabilidade"** em cada computador, com os dados do inventário: fabricante, modelo, número de série, patrimônio, processador, memória, disco e sistema operacional.
- **Termos de entrega e de devolução** com textos próprios. O texto aparece na tela antes da assinatura e é o mesmo que vai para o PDF.
- **Checklist de acessórios** e campo de **observações** sobre o estado do equipamento.
- **Assinatura na tela** com o dedo, uma caneta ou o mouse. Funciona no computador, no tablet e no celular.
- **PDF de uma página**, arquivado na aba **Documentos** do computador e aberto direto no navegador.
- **Registro da assinatura:** o PDF guarda a data, a hora, o técnico presente, o endereço IP e um **código do documento**.
- **O equipamento é atualizado sozinho:**
  - na entrega, o colaborador vira o usuário do equipamento e o status passa a **Em uso**;
  - na devolução, o usuário fica vazio e o status passa a **Em estoque**.
  - Tudo fica registrado no **histórico**.
- **Envio por e-mail para assinatura:**
  - o colaborador recebe um link, entra no GLPI com o próprio usuário, lê o termo, marca "li e concordo" e assina;
  - o equipamento só é atualizado depois da assinatura;
  - o colaborador recebe uma cópia em PDF, e quem enviou recebe um aviso.
- **PDF para assinar no papel**, sem gravar nada, para quem prefere a via física.
- **Linha do tempo** com todos os termos já gerados para o equipamento, e lista dos que **aguardam assinatura**, com opções para reenviar e cancelar.
- **Seguro:**
  - só gera termos quem pode alterar o computador;
  - o envio é protegido pelo token CSRF do GLPI;
  - os dados são validados no servidor;
  - a assinatura é conferida como imagem PNG;
  - o link do e-mail exige login no GLPI e só abre para o colaborador destinatário. Outro usuário logado não vê nem assina o termo.
- **Os PDFs ficam na aba Documentos do GLPI:** remover o plugin não apaga nenhum termo assinado.

---

## Instalação

1. Baixe o arquivo `assetterms-<versão>.zip` da [última Release](https://github.com/GustavoMS0/GLPI-AssetTerms/releases/latest).
2. Extraia na pasta `plugins` do GLPI. O resultado deve ser `plugins/assetterms/setup.php`.
3. Instale e ative o plugin. Escolha uma das formas:
   - pela tela, em **Configurar › Plugins** (*Asset Terms*);
   - pelo console:
     ```bash
     cd /var/www/glpi
     sudo -u www-data php bin/console plugin:install --username=glpi assetterms
     sudo -u www-data php bin/console plugin:activate assetterms
     ```

> O instalador automático [Install-Gm](https://github.com/GustavoMS0/Install-Gm) já baixa e ativa o plugin.

### E-mail

Para o envio por e-mail, o GLPI precisa estar configurado para mandar e-mails:

- **Servidor de e-mail e remetente:** em **Configurar › Notificações › Configuração das notificações por e-mail**. Use o botão de teste da própria tela para conferir.
- **URL do GLPI:** em **Configurar › Geral › URL da aplicação**. O link do e-mail usa esse endereço, então ele precisa abrir para os colaboradores, por exemplo `http://glpi.empresa.local`.
- **E-mail do colaborador:** em **Administração › Usuários**.

### Status "Em uso" e "Em estoque"

O plugin sugere esses status ao gerar o termo. Se eles não existirem, crie-os em **Configurar › Listas suspensas › Status dos itens**. O Install-Gm já cria os dois.

---

## Como usar

1. Abra o computador em **Ativos › Computadores** e clique na aba **Termo de Responsabilidade**.
2. Escolha **Entrega ao colaborador** ou **Devolução à TI**.
3. Selecione o **colaborador**, marque os **acessórios** e, se precisar, escreva as **observações**.
4. O colaborador lê o termo e **assina na tela**.
5. Clique em **Gerar e arquivar termo**.

Para assinar no papel, clique em **PDF para assinar no papel**, imprima e depois anexe a via digitalizada na aba **Documentos**.

### Assinatura pelo link do e-mail

1. Na aba, preencha o termo como acima e clique em **Enviar por e-mail para assinatura**. Não é preciso assinar no quadro.
2. O colaborador recebe o e-mail **"Termo de entrega do equipamento … para assinatura"** e clica em **Ler e assinar o termo**.
3. Ele entra no GLPI com o próprio usuário. Se já estiver logado, a página abre direto.
4. Ele lê o termo, marca **"Li o termo e concordo"** e assina com o dedo, uma caneta ou o mouse.
5. Ao assinar:
   - o PDF é arquivado na aba **Documentos**;
   - o usuário e o status do equipamento são atualizados;
   - o colaborador recebe uma cópia em PDF, e quem enviou recebe um aviso.

Enquanto o termo não é assinado, ele aparece em **Aguardando assinatura** na aba do computador, com os botões:
- **Reenviar:** manda o mesmo link de novo, para o e-mail atual do colaborador;
- **Cancelar:** o link deixa de funcionar.

| Situação | O que acontece ao abrir o link |
|---|---|
| Sem login | vai para a tela de login do GLPI e, depois de entrar, volta para o termo |
| Logado com outro usuário | "Termo não disponível": não mostra nem permite assinar |
| Termo já assinado | mostra a data da assinatura |
| Termo cancelado pela TI | avisa que foi cancelado |

O PDF traz:
- o cabeçalho, com a empresa, a data e o código do documento;
- o colaborador, com o nome, a matrícula e o e-mail;
- o equipamento;
- os acessórios e as observações;
- a declaração;
- as assinaturas do colaborador e da TI, com a cidade e a data por extenso.

A empresa é a entidade do computador. A cidade vem do campo **Cidade** da entidade, em **Administração › Entidades**.

---

## Texto do termo

### Entrega

> Declaro que recebi da **[empresa]**, em regime de comodato e para uso exclusivo no exercício das minhas atividades profissionais, o equipamento e os acessórios descritos neste termo, em perfeito estado de conservação e funcionamento, ressalvadas as observações registradas. Comprometo-me a:
>
> I. utilizá-lo somente para fins profissionais, conforme a Política de Segurança da Informação e as normas internas;
> II. zelar pela sua guarda e conservação, sem emprestá-lo, cedê-lo ou permitir o uso por pessoas não autorizadas;
> III. não instalar programas não autorizados nem alterar as configurações de segurança, os componentes ou a etiqueta de patrimônio;
> IV. comunicar imediatamente à TI qualquer defeito, dano, perda, furto ou roubo, apresentando boletim de ocorrência nos casos de furto ou roubo;
> V. devolvê-lo com os acessórios, nas mesmas condições em que o recebi, ressalvado o desgaste natural pelo uso normal, sempre que solicitado, na sua substituição ou no encerramento do meu vínculo com a empresa.
>
> Estou ciente de que o equipamento e os dados corporativos nele armazenados pertencem à empresa, podendo ser acessados, monitorados ou removidos conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Em caso de dano causado por dolo ou culpa (negligência, imprudência ou imperícia), perda ou extravio, autorizo o desconto do valor correspondente, nos termos do art. 462, § 1º, da Consolidação das Leis do Trabalho (CLT).

### Devolução

> Declaro que, nesta data, devolvi à **[empresa]** o equipamento e os acessórios descritos neste termo, conferidos na presença do(a) responsável pela TI.
>
> - O estado do equipamento e de cada acessório na devolução é o registrado no campo de observações deste termo.
> - Acessórios não listados como devolvidos foram considerados ausentes na conferência.
> - Removi ou entreguei à TI os arquivos pessoais que mantinha no equipamento, quando havia.
>
> Estou ciente de que os dados armazenados no equipamento poderão ser apagados para a sua reutilização, conforme a Política de Segurança da Informação e a Lei Geral de Proteção de Dados (Lei nº 13.709/2018), e de que danos ou ausências registrados neste termo serão tratados conforme o termo de entrega e as normas internas.

### Antes de usar na sua empresa

- **Revisão jurídica:** este texto é um modelo. Peça ao RH ou ao jurídico para revisá-lo, principalmente a autorização de desconto.
  - O art. 462, § 1º, da CLT só permite descontar o prejuízo do salário em dois casos: quando houve **dolo** do empregado, ou quando o desconto foi **combinado** com ele, o que o termo faz para os casos de culpa.
- **Assinatura na tela ou pelo link:** é uma **assinatura eletrônica simples**, não uma assinatura digital com certificado ICP-Brasil. Pelo link, ela fica mais forte como prova, porque o colaborador entra com o próprio usuário e senha e o PDF registra o usuário, o IP e a data. Se a empresa exigir certificado digital, gere o PDF para assinatura e assine com a ferramenta de certificado da empresa.

---

## Personalizar

Tudo fica em [`assetterms/inc/term.class.php`](assetterms/inc/term.class.php):

| O quê | Onde |
|---|---|
| Texto dos termos (tela e PDF) | função `clausula()` |
| Lista de acessórios | constante `CHECKLIST` |
| Acessórios marcados por padrão | constante `CHECKLIST_DEFAULT` |
| Layout do PDF | função `buildPdf()` |

---

## Permissões

| Perfil | Vê a aba | Gera, envia, reenvia e cancela termos |
|---|---|---|
| Pode **ver** computadores | sim, só a lista de termos | não |
| Pode **alterar** o computador | sim | sim |
| **Qualquer usuário**, inclusive do autoatendimento | não | assina pelo link só os termos enviados para ele |

O PDF usa o tipo de documento **PDF**, que já vem liberado no GLPI. Ele fica em **Configurar › Listas suspensas › Tipos de documento**.

---

## Testes

Validado no GLPI 11.0.10 e no GLPI 10.0.28, com o navegador:

| Cenário | Resultado |
|---|---|
| Abrir a aba de um computador inventariado | formulário com os dados do inventário ✅ |
| Entrega assinada na tela | PDF arquivado e vinculado, usuário vinculado, status **Em uso** ✅ |
| PDF arquivado | abre no navegador, em uma página, com a assinatura e o registro dela ✅ |
| Trocar para devolução | o texto, os rótulos e o status sugerido (**Em estoque**) mudam ✅ |
| Devolução sem assinatura | pede confirmação, arquiva, desvincula o usuário e põe **Em estoque** ✅ |
| PDF para assinar no papel | gerado sem gravar nada ✅ |
| Envio sem token CSRF ou com assinatura que não é PNG | recusado ✅ |
| Nome com acento e apóstrofo (ex.: Maria D'Ávila) | correto na tela, no PDF e no histórico ✅ |
| Celular (390 px) | aba e área de assinatura utilizáveis ✅ |
| Envio por e-mail | e-mail para o colaborador com o link; equipamento só muda depois da assinatura ✅ |
| Link aberto sem login | tela de login do GLPI e, depois de entrar, volta para o termo ✅ |
| Link aberto por outro usuário logado | não mostra o termo; assinar pela API dá erro 403 ✅ |
| Assinar sem "li e concordo" ou sem desenhar | bloqueado ✅ |
| Colaborador do autoatendimento assina pelo link | PDF arquivado, usuário e status atualizados, cópia em PDF para o colaborador e aviso para o técnico ✅ |
| Abrir o link de novo depois de assinar | "Termo já assinado", sem permitir uma segunda assinatura ✅ |
| Reenviar e cancelar | reenvia o mesmo link; depois de cancelar, o link não permite assinar ✅ |
| Colaborador sem e-mail cadastrado | avisa e não envia ✅ |

---

## Licença

[GPLv3 ou posterior](LICENSE), a mesma do GLPI.
