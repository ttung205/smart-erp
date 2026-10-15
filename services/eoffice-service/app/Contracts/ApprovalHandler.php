<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Nghiệp vụ riêng của từng loại đơn khi người duyệt ra quyết định. */
interface ApprovalHandler
{
    /** @param  string  $decision  APPROVED | REJECTED | RETURNED */
    public function onDecision(Model $approvable, string $decision): void;
}
