<?php namespace App\Http\Controllers;

use Utils;
use Response;
use Input;
use Auth;
use App\Models\Client;
use App\Ninja\Repositories\ClientRepository;
use App\Http\Requests\CreateClientRequest;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\ClientTransformer;

class ClientApiController extends BaseAPIController
{
    protected $clientRepo;

    public function __construct(ClientRepository $clientRepo)
    {
        parent::__construct();

        $this->clientRepo = $clientRepo;
    }

    public function ping()
    {
        $headers = Utils::getApiHeaders();

        return Response::make('', 200, $headers);
    }

    /**
     * @SWG\Get(
     *   path="/clients",
     *   summary="List of clients",
     *   tags={"client"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with clients",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $clients = Client::scope()
                    ->with($this->getIncluded())
                    ->orderBy('created_at', 'desc')
                    ->paginate();

        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = Client::scope()->paginate();

        $data = $this->createCollection($clients, $transformer, ENTITY_CLIENT, $paginator);

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/clients",
     *   tags={"client"},
     *   summary="Create a client",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Client")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New client",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Client"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateClientRequest $request)
    {
        $client = $this->clientRepo->save($request->input());
        
        $client = Client::scope($client->public_id)
                    ->with('country', 'contacts', 'industry', 'size', 'currency')
                    ->first();

        $transformer = new ClientTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($client, $transformer, ENTITY_CLIENT);

        return $this->response($data);
    }
}
