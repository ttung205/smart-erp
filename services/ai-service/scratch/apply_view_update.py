import sys

path = r"G:\Do_an_lien_nganh\smart-erp\frontend\src\components\ai\AiConversationView.vue"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Update template
target_tmpl = """            <div v-else class="leading-relaxed" :class="large ? 'ai-answer-lg' : ''" v-html="renderMarkdown(m.content)"></div>
            <div v-if="m.tools?.length" class="mt-1.5 text-slate-400" :class="large ? 'text-xs' : 'text-[10px]'">"""

repl_tmpl = """            <div v-else class="leading-relaxed" :class="large ? 'ai-answer-lg' : ''" v-html="renderMarkdown(m.content)"></div>

            <!-- Generative UI Action Cards -->
            <ProcurementActionCard
              v-if="m.action_schema && m.action_schema.action_type === 'ACTION_CARD_PROCUREMENT' && !m.action_dismissed"
              :action="m.action_schema"
              @dismiss="m.action_dismissed = true"
            />
            <SalesActionCard
              v-else-if="m.action_schema && m.action_schema.action_type === 'ACTION_CARD_SALES_ORDER' && !m.action_dismissed"
              :action="m.action_schema"
              @dismiss="m.action_dismissed = true"
            />

            <div v-if="m.tools?.length" class="mt-1.5 text-slate-400" :class="large ? 'text-xs' : 'text-[10px]'">"""

if target_tmpl in content:
    content = content.replace(target_tmpl, repl_tmpl)
elif target_tmpl.replace("\n", "\r\n") in content:
    content = content.replace(target_tmpl.replace("\n", "\r\n"), repl_tmpl.replace("\n", "\r\n"))
else:
    print("Error: target_tmpl not found")
    sys.exit(1)

# 2. Add imports
target_import = "import { renderMarkdown } from './aiMarkdown';"
repl_import = """import { renderMarkdown } from './aiMarkdown';
import ProcurementActionCard from './ProcurementActionCard.vue';
import SalesActionCard from './SalesActionCard.vue';"""

if target_import in content:
    content = content.replace(target_import, repl_import)
elif target_import.replace("\n", "\r\n") in content:
    content = content.replace(target_import.replace("\n", "\r\n"), repl_import.replace("\n", "\r\n"))
else:
    print("Error: target_import not found")
    sys.exit(1)

# 3. Add TOOL_LABELS
target_labels = "suggest_credit_action: 'Đề xuất xử lý công nợ'"
repl_labels = """suggest_credit_action: 'Đề xuất xử lý công nợ',
  procurement_fast_order: 'Nguồn hàng & Đặt mua nhanh',
  instant_quote_check: 'Báo giá sỉ & Kiểm tra tín dụng'"""

if target_labels in content:
    content = content.replace(target_labels, repl_labels)
elif target_labels.replace("\n", "\r\n") in content:
    content = content.replace(target_labels.replace("\n", "\r\n"), repl_labels.replace("\n", "\r\n"))
else:
    print("Error: target_labels not found")
    sys.exit(1)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Successfully updated AiConversationView.vue!")

