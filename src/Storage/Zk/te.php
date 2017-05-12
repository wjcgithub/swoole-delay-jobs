<?php
class ZookeeperDemo extends Zookeeper {

    public function watcher( $i, $type, $key ) {
        echo "Insider Watcher\n" ;

        // Watcher gets consumed so we need to set a new one
        $this->get( '/test', array ($this, 'watcher' ) );
    }
}
$zoo = new ZookeeperDemo( '127.0.0.1:2181' );
$zoo->get( '/test', array ($zoo, 'watcher' ) );
while ( true ) {
    echo '.' ;
    sleep(2);
}

//leader与worker任务的分配：
class Worker extends Zookeeper {

    const CONTAINER = '/cluster' ;

    protected $acl = array(
        array (
            'perms' => Zookeeper:: PERM_ALL,
            'scheme' => 'world' ,
            'id' => 'anyone' ) );

    private $isLeader = false;

    private $znode ;

    public function __construct( $host = '', $watcher_cb = null , $recv_timeout = 10000 ) {
        parent:: __construct( $host, $watcher_cb, $recv_timeout );
    }

    public function register() {
        if( ! $this->exists( self ::CONTAINER ) ) {
            $this->create( self ::CONTAINER , null, $this-> acl );
        }

        $this->znode = $this->create( self ::CONTAINER . '/w-' ,
            null ,
            $this->acl,
            Zookeeper::EPHEMERAL | Zookeeper::SEQUENCE );

        $this-> znode = str_replace( self ::CONTAINER .'/' , '' , $this-> znode );

        printf( "I'm registred as: %s\n", $this-> znode );

        $watching = $this->watchPrevious();

        if( $watching == $this-> znode ) {
            printf( "Nobody here, I'm the leader\n" );
            $this->setLeader( true );
        }
        else {
            printf( "I'm watching %s\n" , $watching );
        }
    }

    public function watchPrevious() {
        $workers = $this->getChildren( self ::CONTAINER );
        sort( $workers );
        $size = sizeof( $workers );
        for( $i = 0 ; $i < $size ; $i++ ) {
            if( $this-> znode == $workers[ $i ] ) {
                if ( $i > 0 ) {
                    $this->get( self ::CONTAINER . '/' . $workers[ $i - 1 ], array ( $this, 'watchNode' ) );
                    return $workers[ $i - 1 ];
                }

                return $workers[ $i ];
            }
        }

        throw new Exception(  sprintf( "Something went very wrong! I can't find myself: %s/%s",
            self ::CONTAINER ,
            $this-> znode ) );
    }

    public function watchNode( $i, $type, $name ) {
        $watching = $this->watchPrevious();
        if( $watching == $this-> znode ) {
            printf( "I'm the new leader!\n" );
            $this->setLeader( true );
        }
        else {
            printf( "Now I'm watching %s\n" , $watching );
        }
    }

    public function isLeader() {
        return $this-> isLeader ;
    }

    public function setLeader($flag) {
        $this-> isLeader = $flag;
    }

    public function run() {
        $this->register();

        while( true ) {
            if( $this->isLeader() ) {
                $this->doLeaderJob();
            }
            else {
                $this->doWorkerJob();
            }

            sleep( 2 );
        }
    }

    public function doLeaderJob() {
        echo "Leading\n" ;
    }

    public function doWorkerJob() {
        echo "Working\n" ;
    }
}
$worker = new Worker( '127.0.0.1:2181' );
$worker->run();