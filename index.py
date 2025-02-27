from flask import Flask, redirect, session, url_for, render_template, request, jsonify
from flask_mysqldb import MySQL
from flask_hashing import Hashing


app = Flask(__name__)

app.config['MYSQL_HOST'] = 'hackathon.dckap.net'
app.config['MYSQL_USER'] = 'rhino3d'
app.config['MYSQL_PASSWORD'] = 'Hin#0@#cdjjj23123'
app.config['MYSQL_DB'] = 'dcip'

mysql = MySQL(app)

app.secret_key = 'Rhino3dTheF@nt@stic4'

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        details = request.form
        hashing = Hashing(app)
        username = details['username']
        passwordhash = hashing.hash_value(details['password'], salt='rhino3d')
        cur = mysql.connection.cursor()
        query = "SELECT employeeid FROM dcip_employees WHERE employeeid = %s AND password = %s"
        param = (username, passwordhash)
        cur.execute(query, param)
        userdata = cur.fetchall()
        employeeid = '';
        for row in userdata: 
            employeeid = row[0]
        if(len(employeeid) > 0):   
            session['employeeid'] = employeeid 
            return redirect(url_for('leaderboard'))
        else:
            return redirect(url_for('login')) 
    else:
        return render_template('index.html', title='DCKAP Community Insider Program')

@app.route('/', methods=['GET', 'POST'])
def index():
    if 'employeeid' in session:
        return redirect(url_for('leaderboard'))
    else:
        return redirect(url_for('login'))

@app.route('/category',methods=['GET', 'POST'])
def category():
    return render_template('category.html')

@app.route('/leaderboard', methods=['GET', 'POST'])
def leaderboard():
    cur = mysql.connection.cursor()
    query = "SELECT id,name,designation,total_points,employeeid FROM dcip_employees where total_points > 0"
    cur.execute(query)
    employeesdata = cur.fetchall()
    return render_template('leaderboard.html',result=employeesdata)

@app.route('/karma-history/<int:emp_id>')
def karmahistory(emp_id):
    cur = mysql.connection.cursor()
    query = ('SELECT it.item_name, tr.asssigned_point, tr.created_at, em.name, em.employeeid, em.total_points FROM dcip_transactions as tr left join dcip_employees as em on tr.employee_id = em.id '
    'left join dcip_items as it on tr.item_id = it.id where tr.asssigned_point > 0 and em.id = %s order by tr.created_at desc') 
    param = (emp_id,)
    cur.execute(query,param)
    karmahistory = cur.fetchall()
    # return jsonify(karmahistory)
    return render_template('karmahistory.html',result=karmahistory)    


@app.route('/karma-list')
def karmalist():
    cur = mysql.connection.cursor()
    query = "SELECT id,item_name,point FROM dcip_items"
    cur.execute(query)
    itemsdata = cur.fetchall()
    # return jsonify(itemsdata)
    return render_template('items.html',result=itemsdata)


if __name__ == '__main__':
    app.run()
