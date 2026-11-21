<?php
declare(strict_types=1);

/** FAQ: questions grouped by category, bilingual, ordered. One page with the list and an add / edit form. */
class AdminFaqController extends Controller
{
    public const CATEGORIES = ['general', 'order', 'delivery', 'payment', 'returns', 'prescription', 'account'];

    public function index(): void
    {
        $editId = (int) Helpers::str('edit');
        $edit = $editId ? $this->db->fetch('SELECT * FROM faqs WHERE id = :id', ['id' => $editId]) : null;
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/cms/faqs', [
            'title'  => __('faqs'),
            'faqs'   => $this->db->fetchAll('SELECT * FROM faqs ORDER BY FIELD(category, ' . implode(',', array_map(static fn($c) => "'{$c}'", self::CATEGORIES)) . '), sort_order, id'),
            'edit'   => $edit,
            'input'  => is_array($old) ? $old : ($edit ?? ['category' => 'general', 'sort_order' => '0', 'is_active' => '1']),
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function save(): void
    {
        $in = $this->postData();
        $id = (int) ($in['id'] ?? 0);
        $errors = $this->validate($in, [
            'category'    => 'required|in:' . implode(',', self::CATEGORIES),
            'question_en' => 'required|max:255',
            'question_bn' => 'max:255',
            'answer_en'   => 'required|max:5000',
            'answer_bn'   => 'max:5000',
            'sort_order'  => 'integer',
        ]);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/faqs' . ($id ? '?edit=' . $id : ''));
        }
        $data = [
            'category'    => $in['category'],
            'question_en' => $in['question_en'],
            'question_bn' => ($in['question_bn'] ?? '') !== '' ? $in['question_bn'] : null,
            'answer_en'   => Html::clean($in['answer_en']),
            'answer_bn'   => ($in['answer_bn'] ?? '') !== '' ? Html::clean($in['answer_bn']) : null,
            'sort_order'  => (int) ($in['sort_order'] ?? 0),
            'is_active'   => !empty($in['is_active']) ? 1 : 0,
        ];
        if ($id && $this->db->fetchColumn('SELECT 1 FROM faqs WHERE id = :id', ['id' => $id])) {
            $this->db->update('faqs', $data, 'id = :id', ['id' => $id]);
        } else {
            $id = $this->db->insert('faqs', $data);
        }
        Audit::log('faqs.save', 'faqs', $id, null, ['question' => $data['question_en']]);
        flash('success', __('faq_saved'));
        redirect('admin/faqs');
    }

    public function destroy(string $id): void
    {
        $this->db->query('DELETE FROM faqs WHERE id = :id', ['id' => (int) $id]);
        Audit::log('faqs.delete', 'faqs', (int) $id, null, null);
        flash('success', __('faq_deleted'));
        redirect('admin/faqs');
    }
}
