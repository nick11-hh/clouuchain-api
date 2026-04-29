<?php


namespace App\Services\Client;


use App\Models\CTUMessage;
use App\Models\CTUUserMessage;
use Illuminate\Support\Arr;

class CTUUserMessageService extends BaseService
{
    public $filterRules = [
        'is_read' => ['=', 'is_read']
    ];

    public function __construct(CTUUserMessage $ctuUserMessage)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new $ctuUserMessage();
        $this->query = $ctuUserMessage::query();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query
            ->with(['ctuMessage'])
            ->where('user_id', getCustomId())
            ->orderBy('is_read')
            ->orderByDesc('id');
        return parent::index();
    }

    public function show(int $id)
    {
        $this->query->where('user_id', getCustomId());
        return $this->query->findOrFail($id);
    }

    public function read($ids)
    {
        /**@var \App\Models\CTUUserMessage $message */
        $messages = $this->model::query()
            ->where('is_read', 0)
            ->where('user_id', getCustomId())
            ->whereIn('id', Arr::wrap($ids))
            ->get();

        $messages->each(function ($message) {
            $message->update(['is_read' => 1]);
            $message->ctuMessage()->increment('read_count');
        });

        return true;
    }

    public function readAll()
    {
        $query = $this->model::query()
            ->where('is_read', 0)
            ->where('user_id', getCustomId());

        $countQuery = clone $query;
        $countQuery->chunkById(500, function ($messages) {
            $messages->each(function ($message) {
                $message->ctuMessage()->increment('read_count');
            });
        });

        $query->update(['is_read' => 1]);

        return true;
    }

    public function destroy($ids)
    {
        return $this->model::query()
            ->where('is_read', 1)
            ->where('user_id', getCustomId())
            ->whereIn('id', Arr::wrap($ids))
            ->delete();
    }

    public function destroyReadAll()
    {
        return $this->model::query()
            ->where('is_read', 1)
            ->where('user_id', getCustomId())
            ->delete();
    }
}
