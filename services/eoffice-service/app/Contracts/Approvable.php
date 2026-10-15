<?php

namespace App\Contracts;

/** Chứng từ đi qua luồng duyệt chung (approval_steps / approval_histories). */
interface Approvable
{
    /** Thông tin tóm tắt hiển thị ở hộp "Chờ tôi duyệt". */
    public function approvalSummary(): array;
}
