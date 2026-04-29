<?php

namespace App\Services\Client;

use App\Models\ExpressLineModel;
use App\Models\MemberLevel;
use App\Models\SalePrice;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Collection;

class PriceDisCountService
{
    protected ?User $user = null;

    protected ExpressLineModel $expressLine;

    protected string $discount = '1';

    protected int $type = 1;

    protected string $name = '';

    /**
     * @param int $expId
     * @return array
     */
    public function query(int $expId)
    {
        $expressLine = ExpressLineModel::query()->findOrFail($expId);

        $counter = (new self())
            ->setExpressLine($expressLine)
            ->setUser(auth('api')->user())
            ->getPriceDiscount();

        return ['name' => $counter->name, 'discount' => $counter->discount];
    }

    /**
     * @return PriceDisCountService
     */
    public function getPriceDiscount()
    {
        $user = $this->user;

        if (!$user) {
            return $this;
        }

        /** @var UserGroup $group */
        $group = $user->load(['group', 'tags'])->group;
        /** @var MemberLevel $memberLevel */
        $memberLevel = $user->member?->level;
        /** @var Collection $tags */
        $tags = $user->tags;

        $prices = SalePrice::query()
            ->where('scope', 0)
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get();

        $prices->add($user->salePrices()
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get());

        $prices->add($group->salePrices()
            ->where('enabled', 1)
            ->orderBy('index')
            ->usable()
            ->get());

        if ($memberLevel) {
            $prices->add($memberLevel->salePrices()
                ->where('enabled', 1)
                ->orderBy('index')
                ->usable()
                ->get());
        }

        if ($tags->isNotEmpty()) {
            $prices->add($tags->map(function ($tag) {
                return $tag->salePrices()
                    ->where('enabled', 1)
                    ->orderBy('index')
                    ->usable()
                    ->get();
            })->flatten()->sortBy('index')->values());
        }

        /** @var SalePrice|null $price */
        $price = $prices->flatten()
            ->values()
            ->filter(fn($v) => $v)
            ->filter(function ($v) {
                $expIds = $v->expressLines()->select('jiyun_express_line.id')->get()->modelKeys();

                return in_array($this->expressLine->id, $expIds);
            })
            ->sortBy(fn($v) => $v->index)
            ->first();

        if (!$price) {
            $this->discount = 1;

            return $this;
        }

        $this->type = $price->discount_type;
        $this->discount = $price->discount;
        $this->name = $price->name;

        return $this;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @return $this
     */
    public function setExpressLine(ExpressLineModel $expressLine): static
    {
        $this->expressLine = $expressLine;

        return $this;
    }
}
